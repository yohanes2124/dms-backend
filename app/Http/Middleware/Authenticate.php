<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, don't redirect - return null to send JSON response
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }
        
        // For web requests, we don't have a login route defined, so return null
        // This will cause a 401 response instead of trying to redirect
        return null;
    }
}