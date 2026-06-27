<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Used to populate assignee dropdowns — agents/admins only.
    public function agents(Request $request)
    {
        if (! $request->user()->isStaff()) {
            abort(403);
        }

        return User::whereIn('role', ['admin', 'agent'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    public function index(Request $request)
    {
        if (! $request->user()->isStaff()) {
            abort(403);
        }

        return User::orderBy('name')->get(['id', 'name', 'email', 'role']);
    }
}
