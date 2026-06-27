<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register either creates a brand new organization (first user becomes admin)
     * or joins an existing organization by slug (defaults to customer).
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'organization_name' => ['required_without:organization_slug', 'nullable', 'string', 'max:255'],
            'organization_slug' => ['required_without:organization_name', 'nullable', 'string', 'exists:organizations,slug'],
        ]);

        if (! empty($data['organization_slug'])) {
            $org = Organization::where('slug', $data['organization_slug'])->firstOrFail();
            $role = 'customer';
        } else {
            $slug = Str::slug($data['organization_name']) . '-' . Str::lower(Str::random(4));
            $org = Organization::create([
                'name' => $data['organization_name'],
                'slug' => $slug,
            ]);
            $role = 'admin';
            $this->seedDefaultSlaPolicies($org->id);
        }

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            'password' => $data['password'],
        ]);
        $user->organization_id = $org->id;
        $user->save();

        $token = $user->createToken('pulsedesk')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('organization'),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::withoutGlobalScopes()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('pulsedesk')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('organization'),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('organization'));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    protected function seedDefaultSlaPolicies(int $orgId): void
    {
        TenantContext::set($orgId);
        $defaults = [
            'urgent' => ['response' => 30, 'resolution' => 240],
            'high' => ['response' => 60, 'resolution' => 480],
            'medium' => ['response' => 240, 'resolution' => 1440],
            'low' => ['response' => 480, 'resolution' => 2880],
        ];
        foreach ($defaults as $priority => $mins) {
            SlaPolicy::create([
                'organization_id' => $orgId,
                'priority' => $priority,
                'response_minutes' => $mins['response'],
                'resolution_minutes' => $mins['resolution'],
            ]);
        }
    }
}
