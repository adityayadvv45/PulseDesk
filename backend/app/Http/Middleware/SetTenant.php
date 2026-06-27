<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the tenant strictly from the authenticated user. The org id is never
 * read from a header, body or query param — this is the core of tenant isolation.
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve via the sanctum guard so the tenant is known *before* route
        // model binding runs — this makes the global scope hide foreign records
        // entirely (404) rather than leaking them to a policy check (403).
        $user = $request->user() ?? $request->user('sanctum');

        if ($user) {
            TenantContext::set($user->organization_id);
        }

        return $next($request);
    }
}
