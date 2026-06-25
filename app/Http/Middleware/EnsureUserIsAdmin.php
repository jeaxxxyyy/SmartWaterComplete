<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            return response()->json([
                'message' => 'Admin access is required.',
            ], 403);
        }

        return $next($request);
    }
}
