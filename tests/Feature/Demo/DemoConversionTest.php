<?php

namespace Tests\Feature\Demo;

use App\Http\Controllers\Auth\GoogleController;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Assessment\AssessmentTestCase;

/**
 * Spec 12.4 -- a demo is lesson 1 of an ordinary course, and signing up opens
 * the rest.
 */
class DemoConversionTest extends AssessmentTestCase
{
    private const CODE = 'harkness-invite-code';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.invitation_code' => self::CODE]);
    }

    #[Test]
    public function after_lesson_one_a_demo_is_asked_to_sign_up(): void
    {
        $course = $this->demo();
        [$first, $second] = [$this->lesson($course, 1), $this->lesson($course, 2)];
        $signUp = route('demo.account.create');

        $this->actingAs($course->student);

        $this->assertSame($signUp, $this->inertiaProps($this->get(route('lessons.show', $first)))['urls']['sign_up']);
        $this->assertSame($signUp, $this->inertiaProps($this->get(route('lessons.homework.show', $first)))['urls']['sign_up']);

        // Lesson 2 is open to the chain but not to the visitor, on every route.
        $this->get(route('lessons.show', $second))->assertRedirect($signUp);
        $this->get(route('lessons.sections.stream', [$second, 1]))->assertForbidden();
        $this->get(route('lessons.homework.show', $second))->assertForbidden();
    }

    #[Test]
    public function a_standard_course_offers_no_sign_up(): void
    {
        $course = $this->activeCourse();
        $first = $this->completeLesson($this->lesson($course, 1));

        $this->actingAs($course->student);

        $this->assertNull($this->inertiaProps($this->get(route('lessons.homework.show', $first)))['urls']['sign_up']);
        $this->get(route('lessons.show', $this->lesson($course, 2)))->assertOk();
    }

    #[Test]
    public function signing_up_opens_the_rest_of_the_same_course(): void
    {
        $course = $this->demo();
        $student = $course->student;
        $tree = app(MemoryTree::class);
        $before = $tree->document($student, $course->workingKnowledgePath())->versionId;

        $this->actingAs($student)->get(route('demo.account.create'))->assertOk();

        $this->post(route('demo.account.store'), $this->submission())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $student->refresh();
        $this->assertSame('standard', $student->plan);
        $this->assertSame('kept@example.com', $student->email);
        $this->assertTrue($student->has_chosen_password);
        $this->assertNotNull($student->tos_accepted_at);
        $this->assertAuthenticatedAs($student);

        // 12.4.4: the course carries on as it was.
        $this->assertNull($course->refresh()->withdrawn_at);
        $this->assertSame($before, $tree->document($student, $course->workingKnowledgePath())->versionId);
        $this->get(route('lessons.show', $this->lesson($course, 2)))->assertOk();
    }

    #[Test]
    public function keeping_with_google_opens_the_rest_of_the_same_course(): void
    {
        // 18.5.1
        $course = $this->demo();
        $student = $course->student;
        $tree = app(MemoryTree::class);
        $before = $tree->document($student, $course->workingKnowledgePath())->versionId;

        $this->actingAs($student);
        $this->google('sub-1', 'kept@example.com')->assertRedirect(route('google.sign-up'));
        $this->assertTrue($this->inertiaProps($this->get(route('google.sign-up')))['keeping']);

        $this->post(route('google.sign-up.store'), ['name' => 'Kept Student', 'tos_accepted' => true, 'invitation_code' => 'not-the-code'])
            ->assertSessionHasErrors('invitation_code');
        $this->assertTrue($student->refresh()->isDemo());
        $this->assertNull($student->googleAccount);

        $this->post(route('google.sign-up.store'), ['name' => 'Kept Student', 'tos_accepted' => true, 'invitation_code' => self::CODE])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $student->refresh();
        $this->assertSame(['standard', 'kept@example.com', 'Kept Student', false], [$student->plan, $student->email, $student->name, $student->has_chosen_password]);
        $this->assertSame('sub-1', $student->googleAccount->sub);
        $this->assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($student);

        // Nothing copied: the same row, the same memory, lesson 2 open.
        $this->assertSame($before, $tree->document($student, $course->workingKnowledgePath())->versionId);
        $this->get(route('lessons.show', $this->lesson($course, 2)))->assertOk();
    }

    #[Test]
    public function keeping_with_a_google_account_that_already_has_an_account_is_refused(): void
    {
        // 18.5.2: by email, and by connection.
        $course = $this->demo();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $member->googleAccount()->create(['sub' => 'member-sub']);

        $this->actingAs($course->student);

        foreach ([['other-sub', 'member@example.com'], ['member-sub', 'changed@example.com']] as [$sub, $email]) {
            $this->google($sub, $email)
                ->assertRedirect(route('demo.account.create'))
                ->assertSessionHasErrors(['google' => GoogleController::TAKEN]);
        }

        $this->post(route('google.sign-up.store'), ['name' => 'Kept Student', 'tos_accepted' => true, 'invitation_code' => self::CODE]);

        $this->assertTrue($course->student->refresh()->isDemo());
        $this->assertNull($course->student->googleAccount);
        $this->assertSame(1, GoogleAccount::query()->count());
    }

    #[Test]
    public function no_code_or_a_wrong_one_leaves_the_account_a_demo_and_says_so_on_the_code_field(): void
    {
        $course = $this->demo();
        $this->actingAs($course->student);

        foreach (['', 'not-the-code'] as $code) {
            $this->post(route('demo.account.store'), $this->submission(['invitation_code' => $code]))
                ->assertSessionHasErrors('invitation_code');
        }

        $this->post(route('demo.account.store'), $this->submission(['tos_accepted' => false]))
            ->assertSessionHasErrors('tos_accepted');

        $this->assertTrue($course->student->refresh()->isDemo());
    }

    #[Test]
    public function a_demo_visitor_who_types_the_exam_address_gets_no_exam(): void
    {
        // Spec 12.6.2.
        $course = $this->demo();
        $this->actingAs($course->student);

        $this->get(route('courses.final-exam.show', $course))->assertRedirect(route('demo.account.create'));
        $this->post(route('courses.final-exam.store', $course))->assertForbidden();

        $this->assertDatabaseMissing('usage_events', ['feature' => 'course.final_exam']);
    }

    #[Test]
    public function a_standard_account_has_no_demo_to_keep(): void
    {
        $course = $this->activeCourse();

        $this->actingAs($course->student)->get(route('demo.account.create'))->assertRedirect(route('dashboard'));
        $this->post(route('demo.account.store'), $this->submission())->assertRedirect(route('dashboard'));

        $this->assertNotSame('kept@example.com', $course->student->refresh()->email);
    }

    /**
     * An ordinary two-lesson course, on the demo plan, with lesson 1 finished.
     */
    private function demo(): Course
    {
        $course = $this->activeCourse();
        $this->completeLesson($this->lesson($course, 1));
        $course->student->forceFill(['plan' => 'demo', 'has_chosen_password' => false])->save();

        return $course->refresh();
    }

    private function google(string $sub, string $email): TestResponse
    {
        Socialite::fake('google', GoogleUser::fake(['id' => $sub, 'email' => $email, 'name' => 'Google Name']));

        return $this->get(route('google.callback'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return [
            'name' => 'Kept Student',
            'email' => 'kept@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'tos_accepted' => true,
            'invitation_code' => self::CODE,
            ...$overrides,
        ];
    }
}
