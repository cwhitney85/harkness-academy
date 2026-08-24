<?php

namespace Database\Factories;

use App\Courses\CourseStatus;
use App\Courses\SkillLevel;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module_slug' => 'math',
            'subtopic_slug' => 'algebra',
            'subtopic_title' => 'Algebra',
            'skill_level' => SkillLevel::Introductory,
            'attempt' => 1,
            'status' => CourseStatus::Created,
            'withdrawn_at' => null,
            'completed_at' => null,
        ];
    }

    public function status(CourseStatus $status): self
    {
        return $this->state(['status' => $status]);
    }

    public function withdrawn(): self
    {
        return $this->state(['withdrawn_at' => now()]);
    }

    /**
     * Passed the final exam: the status records how far it got, the date
     * records that it was cleared (ledger D7b).
     */
    public function completed(): self
    {
        return $this->state([
            'status' => CourseStatus::FinalExam,
            'completed_at' => now(),
        ]);
    }
}
