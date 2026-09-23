<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One free-text subtopic the scope check turned away (spec 4.9.7).
 *
 * Read back only by Admin → Catalog → Refusals (16.1.6), the one place
 * `reason` is rendered: a student never reads the model's words (4.9.6).
 *
 * @property int $id
 * @property int $user_id
 * @property string $typed_name
 * @property string $module_slug
 * @property string $verdict
 * @property string|null $suggested_module_slug
 * @property string $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SubtopicRefusal extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
