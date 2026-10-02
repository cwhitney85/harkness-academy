<?php

namespace App\Http\Middleware;

use App\Admin\ViewingAs;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin views as a student, make every request the student's
 * (spec 13.4).
 *
 * The session stays signed in as the admin; only this request's user becomes
 * the student, for the length of the request, so every controller, policy and shared prop sees exactly what
 * the student would. Runs before `HandleInertiaRequests`, which reads the
 * user when it shares props.
 *
 * What is refused, in order:
 *   - an expired session ends, logged, back to `/admin` (13.4.5);
 *   - `/admin*` is unreachable except step-up and Exit: it sends the admin
 *     back to the student's dashboard, banner and all (13.4.6);
 *   - the student's sign-in -- password, two-factor, passkeys and the
 *     password confirmation guarding them -- in either mode, the same way
 *     (13.4.8);
 *   - in preview, every non-GET request except logout (13.4.1).
 * Logout ends the session, logged, and signs the admin out (13.4.5).
 */
class ViewAsStudent
{
    /**
     * The student's sign-in (13.4.8). Fortify keeps a password confirmation
     * in the session, not on the user, so the admin's own would open every
     * `password.confirm` gate here ([T26](docs/lessons.md)).
     */
    private const SIGN_IN_ROUTES = ['security.edit', 'user-password.update', 'password.*', 'two-factor.*', 'passkey.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $viewing = ViewingAs::in($session);

        if ($viewing === null) {
            return $next($request);
        }

        $student = $viewing->student();

        if ($request->user()?->id !== $viewing->adminId || $student === null || $viewing->hasExpired()) {
            $viewing->end($session, 'expired');

            return redirect()->route('admin.home');
        }

        if ($request->routeIs('logout')) {
            $viewing->end($session, 'logout');

            return $next($request);
        }

        if ($request->routeIs('admin.view-as.step-up', 'admin.view-as.exit')) {
            return $next($request);
        }

        // Back to the student's screens, where the banner's Exit is, rather
        // than a bare 403 with no way out.
        if ($request->is('admin', 'admin/*')) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Exit viewing as a student to use the admin screens.']);

            return redirect()->route('dashboard');
        }

        if ($request->routeIs(self::SIGN_IN_ROUTES)) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'A student\'s sign-in settings stay theirs, even while you view as them.']);

            return redirect()->route('dashboard');
        }

        abort_if($viewing->isPreview() && ! $request->isMethodSafe(), 403, 'Preview is read-only. Step up to act as this student.');

        $admin = $request->user();

        Auth::setUser($student);
        $viewing->enterContext();

        // The guard outlives the request under Octane and in tests, so the
        // admin goes back once this one is handled. Context stays: a streamed
        // turn makes its call after this returns, and still has to be refused
        // in preview and stamped in act-as. Nothing on a stream reads the user.
        try {
            return $next($request);
        } finally {
            Auth::setUser($admin);
        }
    }
}
