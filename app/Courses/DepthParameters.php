<?php

namespace App\Courses;

use App\Courses\Exceptions\UnknownPlan;

/**
 * How much course a student gets (spec 4.5.6).
 *
 * depth = f(skill_level, plan), resolved from `config/courses.php`. Skill
 * level and plan are orthogonal by design: the level decides what is taught
 * and how, the plan decides size, and 101 is deliberately the longest of the
 * three. Anything that reads this to gate teaching quality by price is a bug.
 *
 * Rendered into the `depth_params` variable that `syllabus.generate` and
 * `syllabus.revise` declare (spec 2.3.2). The prompts are told these set the
 * size of the course and nothing else.
 */
final readonly class DepthParameters
{
    /**
     * @param  array{0: int, 1: int}  $lessons  inclusive range
     * @param  array{0: int, 1: int}  $sectionsPerLesson  inclusive range
     */
    private function __construct(
        public array $lessons,
        public array $sectionsPerLesson,
        public int $minutesPerLesson,
    ) {}

    /**
     * Resolve the depth for one skill level on one plan.
     *
     * Pre: `config('courses.depth')` has an entry for this pair.
     * Post: the returned parameters are the configured ones, unmodified.
     *
     * @throws UnknownPlan when the pair is not configured.
     */
    public static function for(SkillLevel $level, string $plan): self
    {
        $depth = config("courses.depth.{$level->value}.{$plan}");

        if (! is_array($depth)) {
            throw UnknownPlan::at($level, $plan);
        }

        return new self(
            lessons: $depth['lessons'],
            sectionsPerLesson: $depth['sections_per_lesson'],
            minutesPerLesson: $depth['minutes_per_lesson'],
        );
    }

    /**
     * The `depth_params` prompt variable (spec 4.5.6.2).
     *
     * A markdown key list, matching the house style of the prompt files it is
     * rendered into. Deliberately not JSON: a structured blob invites the
     * model to echo the structure back rather than read it as instruction.
     */
    public function render(): string
    {
        return implode("\n", [
            '- Lessons: '.$this->range($this->lessons),
            '- Sections per lesson: '.$this->range($this->sectionsPerLesson),
            '- Target minutes per lesson: '.$this->minutesPerLesson,
        ]);
    }

    /**
     * @param  array{0: int, 1: int}  $range
     */
    private function range(array $range): string
    {
        [$min, $max] = $range;

        return $min === $max ? (string) $min : "{$min}-{$max}";
    }
}
