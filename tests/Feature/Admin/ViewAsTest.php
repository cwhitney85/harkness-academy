<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Ai\Gateway;
use App\Courses\CourseStatus;
use App\Models\AdminAction;
use App\Models\Course;
use App\Models\MemoryVersion;
use App\Models\Quiz;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Feature\Lessons\SectionStreamTestCase;

/**
 * Spec 13.4 -- viewing the app as a student: preview reads, act-as writes
 * with a reason, and every step is logged and stamped.
 */
class ViewAsTest extends SectionStreamTestCase
{
    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = $this->lesson->course->student;
    }

    #[Test]
    public function preview_renders_the_students_screens_as_they_see_them(): void
    {
        $this->preview()->assertRedirect(route('dashboard'));

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $this->student->id)
            ->where('viewingAs.mode', 'preview'));

        $this->assertLogged([AdminActionType::ViewAsStarted]);
    }

    #[Test]
    public function preview_refuses_a_lesson_answer_and_writes_nothing(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);
        $frames = [];
        $this->teach(1, $frames);
        $checkpoint = Quiz::query()->where('section_index', 1)->firstOrFail();

        $usage = UsageEvent::query()->count();
        $versions = MemoryVersion::query()->count();

        $this->preview();

        $this->postJson("/lessons/{$this->lesson->id}/checkpoints/{$checkpoint->id}/answers", [
            'question_id' => 'q1',
            'option_id' => 'b',
        ])->assertForbidden();

        $this->post("/lessons/{$this->lesson->id}/questions", ['text' => 'Why?'])->assertForbidden();

        $this->assertSame($usage, UsageEvent::query()->count());
        $this->assertSame($versions, MemoryVersion::query()->count());
    }

    #[Test]
    public function preview_replays_a_taught_section_and_teaches_no_new_one(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);
        $frames = [];
        $this->teach(1, $frames);

        $this->preview();

        $replay = $this->get("/lessons/{$this->lesson->id}/sections/1/stream")->assertOk();
        $this->assertStringContainsString('A variable is a letter', $replay->streamedContent());

        $this->get("/lessons/{$this->lesson->id}/sections/2/stream")->assertForbidden();

        $interviewing = Course::factory()->for($this->student, 'student')->status(CourseStatus::KnowledgeInterview)
            ->create(['subtopic_slug' => 'geometry', 'subtopic_title' => 'Geometry']);
        $this->get("/courses/{$interviewing->id}/knowledge-interview/stream")->assertForbidden();
    }

    #[Test]
    public function the_gateway_refuses_to_start_a_call_in_preview(): void
    {
        $this->fakeModel(fn () => $this->fail('The provider was reached.'));
        Context::add('viewing_as', 'preview');

        try {
            app(Gateway::class)
                ->prompt('explainer.select', [
                    'selection' => 'a phrase',
                    'surrounding_chunk' => 'a paragraph',
                    'subtopic' => 'Intro',
                    'skill_level' => '101',
                ])
                ->feature('lesson.explainer')
                ->generate();

            $this->fail('The call started.');
        } catch (AccessDeniedHttpException) {
            $this->assertSame(0, UsageEvent::query()->count());
        }
    }

    #[Test]
    public function stepping_up_needs_a_reason_and_then_answers_go_through_stamped(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(),
            'Yes -- it can stand for different numbers in different problems.',
        ]);
        $frames = [];
        $this->teach(1, $frames);

        $this->preview();

        $this->post('/admin/view-as/step-up', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/lessons/{$this->lesson->id}/questions", ['text' => 'Why?'])->assertForbidden();

        $this->post('/admin/view-as/step-up', ['reason' => 'Reproducing a bug Maria reported']);

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('viewingAs.mode', 'act_as')
            ->where('viewingAs.reason', 'Reproducing a bug Maria reported'));

        $answer = $this->post("/lessons/{$this->lesson->id}/questions", ['text' => 'Can a letter be more than one number?'])->assertOk();
        $answer->streamedContent();

        $this->assertSame($this->admin->id, UsageEvent::query()->latest('id')->value('acting_user_id'));
        $this->assertSame(
            'Reproducing a bug Maria reported',
            AdminAction::query()->where('action', AdminActionType::ViewAsSteppedUp)->value('reason'),
        );
    }

    #[Test]
    public function act_as_stamps_the_version_the_queued_working_knowledge_update_writes(): void
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(1),
            'A coefficient is the number in front of the letter.',
            $this->checkpointResponse(2),
        ]);
        $first = $second = [];
        $this->teach(1, $first);
        $this->teach(2, $second);

        $this->preview();
        $this->post('/admin/view-as/step-up', ['reason' => 'Checking the lesson-end chain']);

        $this->scriptModel([$this->summaryResponse(), "## Solid\n\n- variable, expression, coefficient.", $this->quizResponse()]);

        $this->post("/lessons/{$this->lesson->id}/complete")->assertSuccessful();

        $wk = MemoryVersion::query()
            ->join('memories', 'memories.id', '=', 'memory_versions.memory_id')
            ->where('memories.path', $this->lesson->course->workingKnowledgePath())
            ->orderByDesc('memory_versions.version')
            ->first(['memory_versions.version', 'memory_versions.acting_user_id']);

        $this->assertGreaterThan(1, $wk->version, 'The update wrote a new version.');
        $this->assertSame($this->admin->id, $wk->acting_user_id);
        $this->assertSame($this->admin->id, UsageEvent::query()->where('feature', 'lesson.wk_update')->value('acting_user_id'));
    }

    #[Test]
    public function start_step_up_and_exit_are_each_logged(): void
    {
        $this->preview();
        $this->post('/admin/view-as/step-up', ['reason' => 'A reason']);
        $this->delete('/admin/view-as')->assertRedirect(route('admin.home'));

        $this->assertLogged([AdminActionType::ViewAsStarted, AdminActionType::ViewAsSteppedUp, AdminActionType::ViewAsExited]);

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $this->admin->id)
            ->where('viewingAs', null));
    }

    #[Test]
    public function a_session_ends_after_sixty_minutes(): void
    {
        $this->preview();

        $this->travel(59)->minutes();
        $this->get('/dashboard')->assertOk();

        $this->travel(2)->minutes();
        $this->get('/dashboard')->assertRedirect(route('admin.home'));

        $exit = AdminAction::query()->latest('id')->first();
        $this->assertSame(AdminActionType::ViewAsExited, $exit->action);
        $this->assertSame(['cause' => 'expired'], $exit->payload);

        $this->get('/admin')->assertOk();
    }

    #[Test]
    public function logging_out_ends_the_session(): void
    {
        $this->preview();

        $this->post('/logout');

        $this->assertGuest();
        $this->assertSame(['cause' => 'logout'], AdminAction::query()->latest('id')->value('payload'));
    }

    #[Test]
    public function the_admin_screens_are_unreachable_while_viewing_except_exit(): void
    {
        $this->preview();

        $this->get('/admin')->assertRedirect(route('dashboard'));
        $this->get('/admin/log')->assertRedirect(route('dashboard'));
        $this->post("/admin/users/{$this->student->id}/view-as")->assertRedirect(route('dashboard'));
        $this->assertSame(1, AdminAction::query()->count(), 'Only the start was logged.');

        $this->delete('/admin/view-as')->assertRedirect(route('admin.home'));
        $this->get('/admin/log')->assertOk();
    }

    /**
     * Spec 13.4.8 -- Fortify's password confirmation lives in the session, so
     * an admin who confirmed their own password would pass every
     * `password.confirm` gate on the student's sign-in ([T26](docs/lessons.md)).
     */
    #[Test]
    public function the_students_sign_in_settings_are_unreachable_in_either_mode(): void
    {
        $this->student->forceFill([
            'two_factor_secret' => encrypt('student-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['student-recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->preview();

        // The admin confirmed their own password a moment ago.
        $this->withSession(['auth.password_confirmed_at' => time()]);

        foreach (['/settings/security', '/user/two-factor-secret-key', '/user/two-factor-recovery-codes', '/user/two-factor-qr-code', '/user/passkeys/options', '/user/confirm-password'] as $url) {
            $this->get($url)
                ->assertRedirect(route('dashboard'))
                ->assertDontSee('student-secret')
                ->assertDontSee('student-recovery-code');
        }

        $this->post('/admin/view-as/step-up', ['reason' => 'Reproducing a bug']);

        $this->delete('/user/two-factor-authentication')->assertRedirect(route('dashboard'));
        $this->post('/user/passkeys', [])->assertRedirect(route('dashboard'));
        $this->put('/settings/password', [])->assertRedirect(route('dashboard'));

        $this->assertTrue($this->student->fresh()->hasEnabledTwoFactorAuthentication());

        // Password resets go to the email, so it stays too; the rest of the
        // profile can still be saved.
        $this->patch('/settings/profile', ['name' => 'Renamed', 'email' => 'admin@example.test'])
            ->assertSessionHasErrors('email');
        $this->patch('/settings/profile', ['name' => 'Renamed', 'email' => $this->student->email])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->student->email, $this->student->fresh()->email);
        $this->assertSame('Renamed', $this->student->fresh()->name);
    }

    #[Test]
    public function another_admin_cannot_be_viewed(): void
    {
        $other = User::factory()->admin()->create();

        $this->actingAs($this->admin)->post("/admin/users/{$other->id}/view-as")->assertForbidden();

        $this->assertSame(0, AdminAction::query()->count());
    }

    private function preview(): TestResponse
    {
        return $this->actingAs($this->admin)->post("/admin/users/{$this->student->id}/view-as");
    }

    /**
     * @param  list<AdminActionType>  $expected
     */
    private function assertLogged(array $expected): void
    {
        $rows = AdminAction::query()->orderBy('id')->get();

        $this->assertSame($expected, $rows->pluck('action')->all());

        foreach ($rows as $row) {
            $this->assertSame($this->admin->id, $row->actor_id);
            $this->assertTrue($row->subject->is($this->student));
        }
    }
}
