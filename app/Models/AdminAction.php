<?php

namespace App\Models;

use App\Admin\AdminActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One admin write (spec 13.2.1). Written only through `ActionLog`.
 *
 * Append-only: the table's trigger refuses UPDATE and DELETE, so `save()` on
 * an existing row and `delete()` both throw.
 *
 * @property int $id
 * @property int $actor_id
 * @property AdminActionType $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $reason
 * @property array<string, mixed> $payload
 * @property Carbon $created_at
 */
class AdminAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'action' => AdminActionType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
