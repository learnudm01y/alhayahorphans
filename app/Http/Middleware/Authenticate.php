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
        if ($request->expectsJson()) {
            return null;
        }

        // Portal-specific login page for the general registration user flow.
        // This ensures session expiry / unauthenticated access to that page
        // never redirects to the default /login.
        if ($request->is('user/general-registration') || $request->is('user/general-registration/*')) {
            return route('user.login.page.index');
        }

        return route('login');
    }
}
