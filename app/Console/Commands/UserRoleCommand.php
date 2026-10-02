<?php

namespace App\Console\Commands;

use App\Admin\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Spec 13.1.3 -- the only way a role is granted or revoked.
 *
 * No web route writes `role`, so becoming an admin needs a shell on the
 * server rather than a session anyone could borrow.
 */
class UserRoleCommand extends Command
{
    protected $signature = 'user:role {email} {role}';

    protected $description = 'Spec 13.1.3: set a user\'s role (student or admin)';

    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->argument('role'));

        if ($role === null) {
            $this->components->error('Role must be one of: '.implode(', ', array_column(Role::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error("No user has the email [{$this->argument('email')}].");

            return self::FAILURE;
        }

        $user->forceFill(['role' => $role])->save();

        $this->components->info("{$user->email} is now {$role->value}.");

        return self::SUCCESS;
    }
}
