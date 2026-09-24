<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Http\Controllers\Controller;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Users (spec 17.1): every account, and where viewing as a student
 * starts (17.1.4).
 *
 * An account the student deleted is listed, marked: what it cost is still
 * real. Its course count includes removed courses, as its page lists them.
 * Cost leaves out calls an admin made acting as the student (13.4.5).
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'plan' => ['nullable', Rule::in(config('courses.plans'))],
        ]);

        $users = User::withTrashed()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->when($filters['plan'] ?? null, fn ($query, $plan) => $query->where('plan', $plan))
            ->addSelect([
                'courses' => Course::withTrashed()->selectRaw('count(*)')->whereColumn('courses.user_id', 'users.id'),
                'cost' => UsageEvent::query()->selectRaw('coalesce(sum(cost_usd), 0)')
                    ->whereColumn('usage_events.user_id', 'users.id')
                    ->whereNull('acting_user_id'),
            ])
            ->orderBy('name')
            ->simplePaginate(50)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->plan,
                'role' => $user->role->value,
                'joined' => $user->created_at?->format('j M Y'),
                'deleted' => $user->trashed(),
                'courses' => (int) $user->getAttribute('courses'),
                'cost' => (float) $user->getAttribute('cost'),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'plan' => $filters['plan'] ?? null,
            ],
            'plans' => config('courses.plans'),
        ]);
    }

    /**
     * One account: its profile, every course it has had, what it cost by
     * feature, and its memory tree read-only, each version naming the course
     * that wrote it and any admin acting as the student (17.1.2, 16.3.4).
     */
    public function show(User $user, MemoryTree $memory): Response
    {
        $usage = UsageEvent::query()->where('user_id', $user->id)->whereNull('acting_user_id');

        $courses = Course::withTrashed()
            ->where('user_id', $user->id)
            ->withCost()
            ->latest('id')
            ->get();

        $history = $memory->history($user);

        $admins = User::withTrashed()
            ->whereIn('id', collect($history)->pluck('versions')->flatten(1)->pluck('acting_user_id')->filter()->unique())
            ->pluck('name', 'id');

        return Inertia::render('admin/users/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->plan,
                'role' => $user->role->value,
                'joined' => $user->created_at?->format('j M Y'),
                'deleted' => $user->deleted_at?->format('j M Y'),
            ],
            'plans' => config('courses.plans'),
            'courses' => $courses->map(fn (Course $course): array => [
                'id' => $course->id,
                'title' => $course->subtopic_title,
                'module' => $course->module_slug,
                'level' => $course->skill_level->value,
                'attempt' => $course->attempt,
                'standing' => $course->standing()->value,
                'removed' => $course->trashed(),
                'created' => $course->created_at->format('j M Y'),
                'cost' => (float) $course->getAttribute('cost'),
            ])->all(),
            'cost' => [
                'total' => (float) (clone $usage)->sum('cost_usd'),
                // The subtopic check, which runs before a course exists, and
                // every call from before usage rows named their course (14.3).
                'unattributed' => (float) (clone $usage)->whereNull('course_id')->sum('cost_usd'),
                'features' => (clone $usage)
                    ->groupBy('feature')
                    ->selectRaw('feature, count(*) as calls, sum(cost_usd) as cost')
                    ->orderByDesc('cost')
                    ->get()
                    ->map(fn (UsageEvent $row): array => [
                        'feature' => $row->feature,
                        'calls' => (int) $row->getAttribute('calls'),
                        'cost' => (float) $row->getAttribute('cost'),
                    ])
                    ->all(),
            ],
            'documents' => array_map(fn (array $document): array => [
                'path' => $document['path'],
                'removed' => $document['removed'],
                'versions' => array_map(fn (array $version): array => [
                    'version' => $version['version'],
                    // `Spanish 101, attempt 2`
                    'course' => with($courses->find($version['course_id']), fn (Course $course): string => "{$course->subtopic_title} {$course->skill_level->value}, attempt {$course->attempt}"),
                    'admin' => $version['acting_user_id'] === null ? null : $admins[$version['acting_user_id']],
                    'when' => $version['created_at']->format('j M Y H:i'),
                    'content' => $version['content'],
                ], $document['versions']),
            ], $history),
        ]);
    }

    /**
     * Move an account to another plan in config (17.1.3, ledger D62).
     *
     * Post: `users.plan` is a config key other than the one it held; one
     * `admin_actions` row. A plan not in config, or the plan it already has,
     * is refused and writes nothing.
     */
    public function plan(Request $request, User $user, ActionLog $log): RedirectResponse
    {
        $plans = array_diff(config('courses.plans'), [$user->plan]);

        $plan = $request->validate(['plan' => ['required', Rule::in($plans)]])['plan'];

        $was = $user->plan;

        $user->forceFill(['plan' => $plan])->save();

        $log->record($request->user(), AdminActionType::PlanChanged, $user, payload: ['plan' => ['was' => $was, 'now' => $plan]]);

        return back();
    }
}
