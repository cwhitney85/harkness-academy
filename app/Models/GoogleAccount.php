<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google sign-in connected to an account (spec 18.2.2). Found by `sub`,
 * never by email (18.2.3).
 *
 * @property int $id
 * @property int $user_id
 * @property string $sub Google's identifier for the person, which never changes
 */
class GoogleAccount extends Model
{
    protected $guarded = [];

    /**
     * The account, deleted or not: a sign-in for a deleted one is refused,
     * not mistaken for a stranger (18.3.2.2).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
