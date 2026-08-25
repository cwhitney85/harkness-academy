<?php

namespace Database\Factories;

use App\Courses\LessonStatus;
use App\Courses\Syllabus\LessonGuidance;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'position' => 1,
            'status' => LessonStatus::Available,
            'guidance_path' => fn (array $attributes) => $this->guidancePath($attributes),
        ];
    }

    public function position(int $position): self
    {
        return $this->state(fn (): array => [
            'position' => $position,
            'status' => LessonStatus::forPosition($position),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function guidancePath(array $attributes): string
    {
        $course = Course::query()->whereKey($attributes['course_id'])->firstOrFail();

        return LessonGuidance::pathFor($course, [
            'position' => $attributes['position'],
            'title' => 'Lesson '.$attributes['position'],
        ]);
    }
}
