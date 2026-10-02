<?php

namespace App\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Context;

/**
 * An admin viewing the app as a student (spec 13.4, ledger D60), held in the
 * admin's session.
 *
 * Two modes. **Preview** -- no reason given -- renders every page as the
 * student sees it and writes nothing: `ViewAsStudent` refuses non-GET
 * requests and the gateway refuses to start a call. **Act-as** -- a reason
 * given at step-up -- lets writes and calls through as the student, each one
 * stamped with the admin's id through `Context`, which reaches queued jobs.
 *
 * Invariant: the session is signed in as `adminId` throughout; the student is
 * only ever the request's user, set per request by `ViewAsStudent`.
 * Every start, step-up and exit leaves one `admin_actions` row (13.4.5).
 */
final class ViewingAs
{
    private const SESSION_KEY = 'viewing_as';

    /** How long a session lasts before it ends on its own (13.4.5). */
    private const MINUTES = 60;

    private function __construct(
        public readonly int $adminId,
        private readonly int $studentId,
        private readonly CarbonImmutable $startedAt,
        /** Why the admin stepped up; null while previewing. */
        public readonly ?string $reason = null,
    ) {}

    public static function in(Session $session): ?self
    {
        $state = $session->get(self::SESSION_KEY);

        return $state === null ? null : new self(
            $state['admin_id'],
            $state['student_id'],
            CarbonImmutable::parse($state['started_at']),
            $state['reason'],
        );
    }

    /**
     * Pre: `$student` is not an admin (13.4.7) and nobody is being viewed.
     */
    public static function start(User $admin, User $student, Session $session): self
    {
        $viewing = new self($admin->id, $student->id, CarbonImmutable::now());

        app(ActionLog::class)->record($admin, AdminActionType::ViewAsStarted, $student);

        return $viewing->keep($session);
    }

    /**
     * Pre: previewing, and `$reason` is not blank (13.4.2).
     */
    public function stepUp(string $reason, Session $session): self
    {
        $viewing = new self($this->adminId, $this->studentId, $this->startedAt, $reason);

        app(ActionLog::class)->record($this->admin(), AdminActionType::ViewAsSteppedUp, $this->student(), $reason);

        return $viewing->keep($session);
    }

    /**
     * @param  'exit'|'expired'|'logout'  $cause
     */
    public function end(Session $session, string $cause): void
    {
        $session->forget(self::SESSION_KEY);

        app(ActionLog::class)->record($this->admin(), AdminActionType::ViewAsExited, $this->student(), payload: ['cause' => $cause]);
    }

    public function isPreview(): bool
    {
        return $this->reason === null;
    }

    public function hasExpired(): bool
    {
        return $this->startedAt->addMinutes(self::MINUTES)->isPast();
    }

    /**
     * Put this request in the mode: the gateway and the stream pre-flights
     * read it, and in act-as the admin's id rides into queued jobs (13.4.3).
     */
    public function enterContext(): void
    {
        Context::add('viewing_as', $this->isPreview() ? 'preview' : 'act_as');

        if (! $this->isPreview()) {
            Context::add('acting_user_id', $this->adminId);
        }
    }

    /**
     * Whether an admin is viewing as the student in this request, in either
     * mode.
     */
    public static function viewing(): bool
    {
        return Context::has('viewing_as');
    }

    /**
     * Whether this request is a preview, and so may write nothing.
     */
    public static function previewing(): bool
    {
        return Context::get('viewing_as') === 'preview';
    }

    /**
     * The admin acting as a student in this request or the job it queued.
     */
    public static function actingUserId(): ?int
    {
        return Context::get('acting_user_id');
    }

    private function admin(): User
    {
        return User::query()->findOrFail($this->adminId);
    }

    public function student(): ?User
    {
        return User::query()->find($this->studentId);
    }

    private function keep(Session $session): self
    {
        $session->put(self::SESSION_KEY, [
            'admin_id' => $this->adminId,
            'student_id' => $this->studentId,
            'started_at' => $this->startedAt->toIso8601String(),
            'reason' => $this->reason,
        ]);

        return $this;
    }
}
