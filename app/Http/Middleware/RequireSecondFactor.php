<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spec 13.1.5 -- `/admin` needs a confirmed second factor, a passkey or a
 * two-factor code. An admin without one is sent to set one up.
 */
class RequireSecondFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication() && ! $user->hasPasskeysEnabled()) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
