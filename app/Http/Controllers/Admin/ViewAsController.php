<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ViewingAs;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Starting, stepping up and leaving a view of a student (spec 13.4).
 *
 * Start and step-up and Exit each write one `admin_actions` row, through
 * `ViewingAs` (13.4.5).
 */
class ViewAsController extends Controller
{
    /**
     * Preview a student: read-only, no AI calls (13.4.1).
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'An admin cannot be viewed as a student.');

        ViewingAs::start($request->user(), $user, $request->session());

        return redirect()->route('dashboard');
    }

    /**
     * Step up from preview to act-as, which needs a reason (13.4.2).
     */
    public function update(Request $request): RedirectResponse
    {
        $viewing = ViewingAs::in($request->session());

        abort_unless($viewing?->isPreview(), 409, 'There is no preview to step up from.');

        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $viewing->stepUp($reason, $request->session());

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        ViewingAs::in($request->session())?->end($request->session(), 'exit');

        return redirect()->route('admin.home');
    }
}
