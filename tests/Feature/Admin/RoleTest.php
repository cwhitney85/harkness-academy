<?php

namespace Tests\Feature\Admin;

use App\Admin\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Spec 13.1.1, 13.1.3 -- role is a column, and only the command writes it. */
class RoleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_account_starts_as_a_student(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));

        $this->assertSame(Role::Student, User::factory()->create()->refresh()->role);
    }

    #[Test]
    public function the_command_grants_and_revokes(): void
    {
        $user = User::factory()->create(['email' => 'colin@harkness.test']);

        $this->artisan('user:role', ['email' => 'colin@harkness.test', 'role' => 'admin'])->assertSuccessful();
        $this->assertTrue($user->refresh()->isAdmin());

        $this->artisan('user:role', ['email' => 'colin@harkness.test', 'role' => 'student'])->assertSuccessful();
        $this->assertFalse($user->refresh()->isAdmin());
    }

    #[Test]
    public function the_command_refuses_an_unknown_role_or_email(): void
    {
        $user = User::factory()->create(['email' => 'colin@harkness.test']);

        $this->artisan('user:role', ['email' => 'colin@harkness.test', 'role' => 'owner'])->assertFailed();
        $this->artisan('user:role', ['email' => 'nobody@harkness.test', 'role' => 'admin'])->assertFailed();

        $this->assertSame(Role::Student, $user->refresh()->role);
    }

    #[Test]
    public function no_web_route_writes_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'Still A Student',
            'email' => $user->email,
            'role' => 'admin',
        ]);

        $this->assertSame('Still A Student', $user->refresh()->name);
        $this->assertSame(Role::Student, $user->role);
    }
}
