<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminActionType;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Log (spec 13.2.3): every admin write, newest first, filterable by
 * actor, action and subject type.
 */
class ActionLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'actor' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::enum(AdminActionType::class)],
            'subject' => ['nullable', 'string'],
        ]);

        $actions = AdminAction::query()
            ->with(['actor:id,name', 'subject'])
            ->when($filters['actor'] ?? null, fn ($query, $actor) => $query->where('actor_id', $actor))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['subject'] ?? null, fn ($query, $subject) => $query->where('subject_type', $subject))
            ->latest('id')
            ->simplePaginate(50)
            ->withQueryString()
            ->through(fn (AdminAction $row): array => [
                'id' => $row->id,
                'when' => $row->created_at->format('j M H:i'),
                'actor' => $row->actor->name,
                'action' => $row->action->value,
                'subject' => $row->subject_type === null ? null : [
                    'type' => $row->subject_type,
                    // The subject may be gone; the row still says what it was.
                    'name' => $row->subject?->getAttribute('name') ?? "#{$row->subject_id}",
                ],
                'reason' => $row->reason,
            ]);

        return Inertia::render('admin/log', [
            'actions' => $actions,
            'filters' => [
                'actor' => $filters['actor'] ?? null,
                'action' => $filters['action'] ?? null,
                'subject' => $filters['subject'] ?? null,
            ],
            'options' => [
                'actors' => User::query()
                    ->whereIn('id', AdminAction::query()->select('actor_id'))
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'actions' => array_column(AdminActionType::cases(), 'value'),
                'subjects' => AdminAction::query()
                    ->whereNotNull('subject_type')
                    ->distinct()
                    ->orderBy('subject_type')
                    ->pluck('subject_type'),
            ],
        ]);
    }
}
