<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admin removal (spec 16.2.1, ledger D57). Every row it hides carries its
 * id; the `admin_actions` row it points at says who removed what, and why.
 *
 * @property int $id
 * @property int $admin_action_id
 */
class Removal extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return BelongsTo<AdminAction, $this>
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(AdminAction::class, 'admin_action_id');
    }

    /**
     * The module, subtopic or course that was removed, whether or not it
     * still is.
     */
    public function subject(): Module|Subtopic|Course
    {
        /** @var Module|Subtopic|Course */
        return $this->action->subject()->withTrashed()->firstOrFail();
    }
}
