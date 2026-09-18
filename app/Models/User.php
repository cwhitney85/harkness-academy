<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Admin\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $tos_accepted_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property string $plan
 * @property Role $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'tos_accepted_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Column defaults this model applies before the database gets a say.
     *
     * `plan` is duplicated from `config/courses.php` and from the migration's
     * schema default, because a freshly created model does not read back what
     * Postgres filled in -- so without this, the first thing to ask a new
     * student for their plan gets null (spec 4.5.6.1). CoursesConfigTest pins
     * all three together.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'plan' => 'standard',
        'role' => 'student',
    ];

    /**
     * Whether this account came in through the demo link and has not been kept
     * (spec 12). Its only effects are the one-lesson depth and, once that
     * lesson is done, the offer to keep the course standing where the exam
     * would (12.4.1).
     */
    public function isDemo(): bool
    {
        return $this->plan === 'demo';
    }

    /**
     * Whether this person may reach `/admin` (spec 13.1). Reads neither plan
     * nor skill level (13.1.4).
     */
    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'tos_accepted_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }
}
