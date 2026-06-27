<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SlaPolicyController extends Controller
{
    public function index()
    {
        $order = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

        return SlaPolicy::get()->sortBy(fn ($p) => $order[$p->priority] ?? 99)->values();
    }

    public function update(Request $request, SlaPolicy $sla_policy)
    {
        if (! $request->user()->isAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'response_minutes' => ['required', 'integer', 'min:1'],
            'resolution_minutes' => ['required', 'integer', 'min:1'],
        ]);

        $sla_policy->update($data);

        return response()->json($sla_policy);
    }
}
