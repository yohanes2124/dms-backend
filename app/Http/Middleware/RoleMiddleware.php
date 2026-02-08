<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        $user = auth()->user();
        
        // Check if user has any of the required roles
        if (!in_array($user->user_type, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions',
                'error_code' => 'INSUFFICIENT_PERMISSIONS',
                'required_roles' => $roles,
                'user_role' => $user->user_type
            ], 403);
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active',
                'error_code' => 'ACCOUNT_INACTIVE',
                'status' => $user->status
            ], 403);
        }

        return $next($request);
    }
}