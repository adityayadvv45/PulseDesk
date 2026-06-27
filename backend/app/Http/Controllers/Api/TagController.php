<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        return Tag::orderBy('name')->get();
    }

    public function store(Request $request)
    {
        if (! $request->user()->isStaff()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $tag = Tag::create([
            'name' => $data['name'],
            'color' => $data['color'] ?? '#64748b',
        ]);

        return response()->json($tag, 201);
    }
}
