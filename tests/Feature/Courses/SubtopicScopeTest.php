<?php

namespace Tests\Feature\Courses;

use App\Admin\Removals;
use App\Courses\SkillLevel;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\SubtopicRefusal;
use App\Models\UsageEvent;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec 4.9 -- a free-text subtopic is checked against its module before any
 * row is written (ledger D42).
 *
 * Every test goes through the HTTP layer, because the thing that was broken
 * was reachable only that way: `CreateCourse` did exactly what it promised and
 * "algebra" under Language was a Language course about algebra, three times
 * out of three, with the student halfway through an interview before anything
 * could have noticed.
 */
class SubtopicScopeTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Algebra is a core mathematics topic with no bearing on language study.';

    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);
    }

    /**
     * 4.9, 4.9.6, 4.9.7 -- the redirect, in our words, with the model's own
     * sentence kept out of them.
     */
    #[Test]
    public function a_subtopic_that_belongs_elsewhere_is_refused_and_its_module_offered(): void
    {
        $student = User::factory()->create();

        $this->scriptModel([$this->wrongModule('math')]);

        $this->actingAs($student)
            ->post(route('courses.store'), $this->submission('language', 'algebra'))
            ->assertRedirect()
            ->assertSessionHas('refused', fn (array $refused): bool => $refused['message']
                === "«algebra» isn't a Language topic. It's one we teach under Math."
                && $refused['note'] === 'If you believe this is a mistake, please try again.'
                && $refused['start_under']['module_slug'] === 'math'
                && $refused['start_under']['module_title'] === 'Math'
                && $refused['typed'] === 'algebra');

        $this->assertSame(0, Course::query()->count(), 'A refusal writes no course row.');

        $refusal = SubtopicRefusal::query()->sole();

        $this->assertSame($student->id, $refusal->user_id);
        $this->assertSame('algebra', $refusal->typed_name);
        $this->assertSame('language', $refusal->module_slug);
        $this->assertSame('wrong_module', $refusal->verdict);
        $this->assertSame('math', $refusal->suggested_module_slug);
        $this->assertSame(self::REASON, $refusal->reason);
    }

    /**
     * 4.9.6 -- the reason is stored and never rendered. The same probes that
     * produced these verdicts called business writing an Economics subject,
     * three times out of three, in a sentence a student would believe.
     */
    #[Test]
    public function the_models_reason_reaches_no_screen(): void
    {
        $this->scriptModel([$this->wrongModule('math')]);

        $student = User::factory()->create();

        $this->actingAs($student)
            ->post(route('courses.store'), $this->submission('language', 'algebra'))
            ->assertRedirect()
            ->assertSessionHas('refused');

        $this->assertStringNotContainsString(self::REASON, json_encode(session('refused')) ?: '');

        $props = $this->inertiaProps($this->actingAs($student)->get(route('courses.create')));

        $this->assertNotNull($props['refused']);
        $this->assertStringNotContainsString(self::REASON, json_encode($props) ?: '');
    }

    /**
     * 4.9.6 -- one submission, and the level comes with it.
     */
    #[Test]
    public function the_offered_module_starts_the_same_subtopic_under_it(): void
    {
        $student = User::factory()->create();

        // Nothing is scripted: "algebra" under Math is a starting point of the
        // module it was typed under, so 4.9.1 answers it without a call.
        $this->scriptModel([]);

        $this->actingAs($student)
            ->post(route('courses.store'), $this->submission('math', 'algebra', 201))
            ->assertRedirect();

        $course = Course::query()->sole();

        $this->assertSame('math', $course->module_slug);
        $this->assertSame('algebra', $course->subtopic_slug);
        $this->assertSame(SkillLevel::Intermediate, $course->skill_level);
    }

    #[Test]
    public function a_subtopic_no_module_teaches_is_refused_with_nothing_to_offer(): void
    {
        $this->scriptModel([[
            'verdict' => 'off_platform',
            'module' => null,
            'reason' => 'Automotive repair is a practical trade skill outside every subject taught here.',
        ]]);

        $this->actingAs(User::factory()->create())
            ->post(route('courses.store'), $this->submission('science', 'how to fix my carburetor'))
            ->assertRedirect()
            ->assertSessionHas('refused', fn (array $refused): bool => $refused['message']
                === "We can't build a course around «how to fix my carburetor.»"
                && $refused['start_under'] === null
                && $refused['note'] === 'If you believe this is a mistake, please try again.');

        $this->assertSame(0, Course::query()->count());
        $this->assertSame('off_platform', SubtopicRefusal::query()->sole()->verdict);
    }

    #[Test]
    public function a_subtopic_that_belongs_here_starts_its_course_and_records_no_refusal(): void
    {
        $this->scriptModel([$this->inScope()]);

        $this->actingAs(User::factory()->create())
            ->post(route('courses.store'), $this->submission('math', 'Knot Theory'))
            ->assertRedirect();

        $this->assertSame('knot-theory', Course::query()->sole()->subtopic_slug);
        $this->assertSame(0, SubtopicRefusal::query()->count(), '4.9.7 -- only a refusal leaves a row.');
    }

    /**
     * 4.9.1 -- the gate spends nothing it need not.
     */
    #[Test]
    public function a_blank_name_and_a_preset_of_the_chosen_module_cost_no_call(): void
    {
        $student = User::factory()->create();

        $this->scriptModel([]);

        $this->actingAs($student)
            ->post(route('courses.store'), $this->submission('math', '。。。'))
            ->assertSessionHasErrors('name');

        $this->actingAs($student)
            ->post(route('courses.store'), $this->submission('math', 'Algebra'))
            ->assertRedirect();

        $this->assertSame('algebra', Course::query()->sole()->subtopic_slug);
        $this->assertSame([], $this->sent, 'Neither path may reach a provider.');
        $this->assertSame(0, UsageEvent::query()->count());
    }

    /**
     * 4.9.2 -- the collision sentence renders only on a cross-module match.
     *
     * Without it the model reads the catalog as an ownership table and refuses
     * `logic` under Math because Philosophy lists it (D42). The 1:1 render
     * fixture pins the shape that has one; this pins the shape that does not.
     */
    #[Test]
    public function the_collision_sentence_renders_only_when_the_slug_is_listed_elsewhere(): void
    {
        $student = User::factory()->create();

        $this->scriptModel([$this->inScope(), $this->inScope()]);

        $this->actingAs($student)->post(route('courses.store'), $this->submission('math', 'logic'));

        $this->assertStringContainsString(
            '«logic» is also one of the starting points listed under Philosophy.',
            $this->sent[0],
        );
        $this->assertStringContainsString('That does not settle the question', $this->sent[0]);

        $this->actingAs($student)->post(route('courses.store'), $this->submission('math', 'Knot Theory'));

        $this->assertStringNotContainsString('starting points listed under', $this->sent[1]);
        $this->assertStringNotContainsString('does not settle the question', $this->sent[1]);
    }

    /**
     * 4.9.3 -- a verdict that cannot be turned into copy is no verdict.
     */
    #[Test]
    public function a_wrong_module_verdict_naming_no_module_we_teach_is_rejected(): void
    {
        $unseeded = ['verdict' => 'wrong_module', 'module' => 'astrology', 'reason' => self::REASON];
        $nowhere = ['verdict' => 'wrong_module', 'module' => null, 'reason' => self::REASON];

        $this->scriptModel([$unseeded, $nowhere]);

        $this->actingAs(User::factory()->create())
            ->post(route('courses.store'), $this->submission('language', 'algebra'))
            ->assertRedirect()
            ->assertSessionHas('refused', fn (array $refused): bool => $refused['message']
                === "We couldn't check that topic just now.");

        $this->assertSame(0, Course::query()->count());
        $this->assertSame(0, SubtopicRefusal::query()->count(), 'Nothing was judged, so nothing is recorded.');
    }

    /**
     * 4.9.5 -- one retry, then fail closed.
     */
    #[Test]
    public function a_provider_that_never_answers_starts_no_course(): void
    {
        $this->scriptModel([new RuntimeException('upstream is down'), new RuntimeException('still down')]);

        $this->actingAs(User::factory()->create())
            ->from(route('courses.create'))
            ->post(route('courses.store'), $this->submission('math', 'Knot Theory'))
            ->assertRedirect(route('courses.create'))
            // The name comes back because the student's own words are what the
            // notice is about; the module and the level come back because a
            // `back()` is a state-preserving visit and the screen still holds
            // them (ledger D28).
            ->assertSessionHas('refused', fn (array $refused): bool => $refused['typed'] === 'Knot Theory'
                && $refused['message'] === "We couldn't check that topic just now."
                && $refused['note'] === null
                && $refused['start_under'] === null);

        $this->assertSame(0, Course::query()->count());
        $this->assertSame(0, SubtopicRefusal::query()->count());
    }

    #[Test]
    public function a_provider_that_answers_on_the_retry_starts_the_course(): void
    {
        $this->scriptModel([new RuntimeException('upstream is down'), $this->inScope()]);

        $this->actingAs(User::factory()->create())
            ->post(route('courses.store'), $this->submission('math', 'Knot Theory'))
            ->assertRedirect();

        $this->assertSame('knot-theory', Course::query()->sole()->subtopic_slug);
    }

    /**
     * 4.9.4 -- the check runs before D7b's question, so a misfiled name never
     * raises a resume prompt about a course that should not exist.
     */
    #[Test]
    public function a_misfiled_name_is_refused_rather_than_offered_a_resume(): void
    {
        $student = User::factory()->create();

        Course::factory()
            ->for($student, 'student')
            ->state(['module_slug' => 'language', 'subtopic_slug' => 'algebra', 'subtopic_title' => 'algebra'])
            ->withdrawn()
            ->create();

        $this->scriptModel([$this->wrongModule('math')]);

        $this->actingAs($student)
            ->post(route('courses.store'), $this->submission('language', 'algebra'))
            ->assertRedirect()
            ->assertSessionHas('refused')
            ->assertSessionMissing('resumable');

        $this->assertSame(1, Course::query()->count(), 'The withdrawn attempt is all there is.');
    }

    /**
     * 16.2.7 -- a removed subtopic's name is refused before the model is
     * asked anything, in the words any untaught topic gets.
     */
    #[Test]
    public function a_removed_subtopics_name_is_refused_without_asking_the_model(): void
    {
        $math = Module::query()->where('slug', 'math')->sole();
        $removed = Subtopic::findOrCreateFor($math, 'quantum-computing', 'Quantum computing');
        app(Removals::class)->remove(User::factory()->admin()->create(), $removed, 'not something we teach');

        $this->scriptModel([['verdict' => 'in_scope', 'module' => null, 'reason' => 'It is maths.']]);

        $this->actingAs(User::factory()->create())
            ->post(route('courses.store'), $this->submission('math', 'Quantum computing'))
            ->assertRedirect()
            ->assertSessionHas('refused', fn (array $refused): bool => $refused['message']
                === "We can't build a course around «Quantum computing.»");

        $this->assertSame([], $this->sent);
        $this->assertFalse(UsageEvent::query()->where('feature', 'subtopic.check')->exists());
        $this->assertSame(0, Course::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function submission(string $module, string $name, int $level = 101): array
    {
        return [
            'source' => 'free_text',
            'module_slug' => $module,
            'subtopic_id' => null,
            'name' => $name,
            'level' => $level,
            'start_over' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wrongModule(string $module): array
    {
        return ['verdict' => 'wrong_module', 'module' => $module, 'reason' => self::REASON];
    }

    /**
     * @return array<string, mixed>
     */
    private function inScope(): array
    {
        return ['verdict' => 'in_scope', 'module' => null, 'reason' => 'A recognised branch of topology.'];
    }

    /**
     * @param  list<array<string, mixed>|RuntimeException>  $responses
     */
    private function scriptModel(array $responses): void
    {
        $this->sent = [];
        $queue = $responses;

        $this->fakeModel(function (string $prompt) use (&$queue): array|string {
            $this->sent[] = $prompt;

            $next = array_shift($queue);

            if ($next instanceof RuntimeException) {
                throw $next;
            }

            return $next ?? 'nothing scripted';
        });
    }
}
