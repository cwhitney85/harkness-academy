<?php

namespace App\Admin;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The one recorder every admin write goes through (spec 13.2.2).
 *
 * Post: exactly one `admin_actions` row, naming the admin who did it.
 */
final class ActionLog
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        User $actor,
        AdminActionType $action,
        ?Model $subject = null,
        ?string $reason = null,
        array $payload = [],
    ): AdminAction {
        return AdminAction::query()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'payload' => $payload,
        ]);
    }
}
