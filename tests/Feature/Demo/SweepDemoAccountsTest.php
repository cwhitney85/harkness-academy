<?php

namespace Tests\Feature\Demo;

use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Assessment\AssessmentTestCase;

/**
 * Spec 12.5.1 -- `demo:sweep` deletes unclaimed demo accounts, and the schema
 * does the rest. Spec 14.3.4 -- what a demo cost stays countable by plan.
 */
class SweepDemoAccountsTest extends AssessmentTestCase
{
    #[Test]
    public function an_old_demo_account_goes_with_everything_it_owns_and_its_spend_stays_countable(): void
    {
        $lesson = $this->lesson();
        $visitor = $lesson->course->student;
        $visitor->forceFill(['plan' => 'demo', 'created_at' => now()->subDays(8)])->save();
        $lesson = $this->completeLesson($lesson);

        $this->actingAs($visitor)
            ->postJson(route('lessons.homework.answers.store', $lesson), ['question_id' => 'h1', 'option_id' => 'a'])
            ->assertOk();

        $spend = UsageEvent::query()->where('user_id', $visitor->id)->count();
        $this->assertGreaterThan(0, $spend);
        $this->assertSame($spend, UsageEvent::query()->where('plan', 'demo')->count());
        $demoCost = UsageEvent::query()->where('plan', 'demo')->sum('cost_usd');

        $this->artisan('demo:sweep')->assertSuccessful();

        $this->assertNull(User::find($visitor->id));
        $this->assertSame($spend, UsageEvent::query()->whereNull('user_id')->count());
        $this->assertSame($demoCost, UsageEvent::query()->where('plan', 'demo')->sum('cost_usd'), 'spec 14.3.4');

        foreach (['courses', 'lessons', 'memories', 'memory_versions', 'quizzes', 'quiz_attempts'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} kept a row");
        }
    }

    #[Test]
    public function a_young_demo_account_and_every_standard_account_are_left_alone(): void
    {
        $young = User::factory()->create(['plan' => 'demo', 'created_at' => now()->subDays(6)]);
        $standard = User::factory()->create(['created_at' => now()->subYear()]);
        $deleted = User::factory()->create(['created_at' => now()->subYear()]);
        $deleted->delete();
        $deletedDemo = User::factory()->create(['plan' => 'demo', 'created_at' => now()->subDays(8)]);
        $deletedDemo->delete();

        $this->artisan('demo:sweep')->assertSuccessful();

        $this->assertNotNull($young->fresh());
        $this->assertNotNull($standard->fresh());

        // 16.2.10: a soft-deleted account is kept -- unless it is an unclaimed
        // demo, which the sweep removes whether or not its visitor deleted it.
        $this->assertTrue($deleted->fresh()?->trashed());
        $this->assertNull($deletedDemo->fresh());
    }
}
