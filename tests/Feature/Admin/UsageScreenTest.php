<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 17.3 -- Admin → Usage: cost over a range, performance per task ×
 * model, and every call, with act-as calls left out unless asked for.
 */
class UsageScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 12:00:00');
        $this->admin = User::factory()->admin()->create(['name' => 'Colin']);
    }

    #[Test]
    public function the_overview_matches_sql_by_day_feature_and_plan(): void
    {
        UsageEvent::factory()->create(['feature' => 'lesson.section', 'plan' => 'standard', 'cost_usd' => 0.25, 'created_at' => '2026-09-20 23:59:59']);
        UsageEvent::factory()->create(['feature' => 'lesson.section', 'plan' => 'demo', 'cost_usd' => 0.5, 'created_at' => '2026-09-21 00:00:00']);
        UsageEvent::factory()->create(['feature' => 'course.syllabus', 'plan' => null, 'cost_usd' => 1, 'created_at' => '2026-09-21 10:00:00']);
        UsageEvent::factory()->create(['feature' => 'course.syllabus', 'cost_usd' => 9, 'created_at' => '2026-09-19 10:00:00']);

        $sql = fn (string $column) => collect(DB::select(<<<SQL
            select {$column} as key, count(*) as calls, sum(cost_usd) as cost from usage_events
            where created_at >= '2026-09-20' and created_at < '2026-09-22' and acting_user_id is null
            group by 1
        SQL))->mapWithKeys(fn ($row) => [(string) $row->key => [(int) $row->calls, (float) $row->cost]])->sortKeys()->all();

        $props = $this->actingAs($this->admin)->get('/admin/usage?from=2026-09-20&to=2026-09-21')->inertiaProps();
        $page = fn (string $key) => collect($props[$key])->mapWithKeys(fn ($row) => [$row['key'] => [$row['calls'], $row['cost']]])->sortKeys()->all();

        $this->assertEquals(1.75, $props['total']);
        $this->assertEquals($sql('created_at::date'), $page('days'));
        $this->assertEquals($sql('feature'), $page('features'));
        $this->assertEquals($sql("coalesce(plan, 'unrecorded')"), $page('plans'));
        $this->assertEquals(['2026-09-20' => [1, 0.25], '2026-09-21' => [2, 1.5]], $page('days'));
        $this->assertEquals(['demo' => [1, 0.5], 'standard' => [1, 0.25], 'unrecorded' => [1, 1]], $page('plans'));
    }

    #[Test]
    public function the_range_defaults_to_the_last_thirty_days_and_refuses_one_that_ends_before_it_starts(): void
    {
        $this->actingAs($this->admin)->get('/admin/usage')->assertInertia(fn (Assert $page) => $page
            ->where('range', ['from' => '2026-08-26', 'to' => '2026-09-24', 'acting' => false]));

        $this->actingAs($this->admin)->get('/admin/usage?from=2026-09-20&to=2026-09-19')->assertSessionHasErrors('to');
    }

    #[Test]
    public function performance_is_per_task_and_model_as_worked_by_hand(): void
    {
        $call = fn (array $row) => UsageEvent::factory()->create(['prompt_key' => 'lesson.section', 'model' => 'model-a', 'tier' => 'mid', ...$row]);

        // Five answered calls: latencies 100..1000 ms, input 1,000 uncached
        // + 500 cached + 500 written, output 200, cost 0.02.
        foreach ([100, 200, 300, 400, 1000] as $latency) {
            $call(['latency_ms' => $latency, 'input_tokens' => 1000, 'cached_input_tokens' => 500, 'cache_write_tokens' => 500, 'output_tokens' => 200, 'cost_usd' => 0.02]);
        }
        // One failed over: answered, by the backup. One failed: slow, empty.
        $call(['latency_ms' => 200, 'input_tokens' => 1000, 'cached_input_tokens' => 500, 'cache_write_tokens' => 500, 'output_tokens' => 200, 'cost_usd' => 0.02, 'outcome' => 'failed_over']);
        $call(['latency_ms' => 60000, 'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 0, 'outcome' => 'failed']);
        // Another model for the same task is its own row.
        $call(['model' => 'model-b', 'latency_ms' => 50, 'cost_usd' => 0.001]);

        $this->actingAs($this->admin)->get('/admin/usage/performance')->assertInertia(fn (Assert $page) => $page
            ->component('admin/usage/performance')
            ->has('rows', 2)
            ->where('rows.0', [
                'task' => 'lesson.section',
                'model' => 'model-a',
                'tier' => 'mid',
                'calls' => 7,
                // Answered latencies 100, 200, 200, 300, 400, 1000: the median
                // is between 200 and 300; p95 sits at 0.95 × 5 = 4.75 of the
                // way along, 400 + 0.75 × 600.
                'median' => 250,
                'p95' => 850,
                'input' => 2000,
                'output' => 200,
                // 3,000 cached of 12,000 input, the failed call's none.
                'cache_share' => 0.25,
                'cost_per_call' => round(0.12 / 7, 8),
                'failure_rate' => round(1 / 7, 4),
                'failover_rate' => round(1 / 7, 4),
            ])
            ->where('rows.1.model', 'model-b'));
    }

    #[Test]
    public function act_as_calls_are_left_out_unless_counted(): void
    {
        UsageEvent::factory()->create(['cost_usd' => 1]);
        UsageEvent::factory()->actingAs($this->admin->id)->create(['cost_usd' => 10]);

        $get = fn (string $url) => $this->actingAs($this->admin)->get($url);

        $get('/admin/usage')->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('range.acting', false));
        $get('/admin/usage?acting=1')->assertInertia(fn (Assert $page) => $page->where('total', 11)->where('range.acting', true));
        $get('/admin/usage/performance')->assertInertia(fn (Assert $page) => $page->where('rows.0.calls', 1));
        $get('/admin/usage/performance?acting=1')->assertInertia(fn (Assert $page) => $page->where('rows.0.calls', 2));
        $get('/admin/usage/events')->assertInertia(fn (Assert $page) => $page->has('events.data', 1));
        $get('/admin/usage/events?acting=1')->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 2)
            ->where('events.data.0.acting', 'Colin'));
    }

    #[Test]
    public function each_event_names_its_student_course_and_lesson_and_the_list_filters(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->for($course)->position(3)->create();

        UsageEvent::factory()->for($course->student)->create(['course_id' => $course->id, 'lesson_id' => $lesson->id, 'feature' => 'lesson.section']);
        UsageEvent::factory()->create(['feature' => 'subtopic.check', 'outcome' => 'failed']);

        $this->actingAs($this->admin)->get('/admin/usage/events?feature=lesson.section')->assertInertia(fn (Assert $page) => $page
            ->component('admin/usage/events')
            ->has('events.data', 1)
            ->where('events.data.0.user', ['id' => $course->user_id, 'name' => $course->student->name])
            ->where('events.data.0.course', $course->id)
            ->where('events.data.0.lesson', 3)
            ->where('options.features', ['lesson.section', 'subtopic.check']));

        $this->actingAs($this->admin)->get('/admin/usage/events?outcome=failed')->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.user', null)
            ->where('events.data.0.course', null));
    }
}
