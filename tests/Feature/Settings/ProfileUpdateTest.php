<?php

namespace Tests\Feature\Settings;

use App\Memory\MemoryTree;
use App\Models\AiModel;
use App\Models\Course;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertTrue($user->fresh()->trashed());
    }

    /**
     * 16.2.10 -- a self-deletion is soft: nothing under the account goes, it
     * cannot sign in, and its email cannot be registered again.
     */
    public function test_a_deleted_account_keeps_everything_and_cannot_sign_in_or_register_again()
    {
        $course = Course::factory()->create();
        $user = $course->student;
        app(MemoryTree::class)->put($course, $course->workingKnowledgePath(), '# Solid: fractions');
        $model = AiModel::query()->firstOrFail();
        UsageEvent::query()->create([
            'user_id' => $user->id, 'course_id' => $course->id, 'feature' => 'lesson', 'prompt_key' => 'lesson.section',
            'provider' => $model->provider, 'tier' => 'mid', 'model' => $model->model, 'ai_model_id' => $model->id,
            'outcome' => 'ok', 'latency_ms' => 100, 'cost_usd' => 0.01,
        ]);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        $this->assertSame(1, Course::query()->where('user_id', $user->id)->count());
        $this->assertSame(['math/algebra/WORKING_KNOWLEDGE.md'], app(MemoryTree::class)->list($user));
        $this->assertSame(1, UsageEvent::query()->where('user_id', $user->id)->count());

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();

        config(['auth.invitation_code' => 'let-me-in']);
        $this->post(route('register.store'), [
            'invitation_code' => 'let-me-in', 'name' => 'Again', 'email' => $user->email,
            'password' => 'password-again', 'password_confirmation' => 'password-again', 'tos_accepted' => true,
        ])->assertSessionHasErrors('email')->assertSessionDoesntHaveErrors(['invitation_code', 'password', 'tos_accepted']);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
