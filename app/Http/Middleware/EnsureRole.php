<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses berdasarkan role. Role tidak cocok => 404 (bukan 403),
 * agar keberadaan area terlarang (mis. panel Super Admin) tidak terbongkar.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyRole($roles)) {
            abort(404);
        }

        return $next($request);
    }
}
