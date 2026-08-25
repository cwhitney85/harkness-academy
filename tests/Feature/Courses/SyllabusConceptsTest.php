<?php

namespace Tests\Feature\Courses;

use App\Courses\Exceptions\UndeclaredConcept;
use App\Courses\Syllabus\SyllabusConcepts;
use Database\Factories\CourseSyllabusFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.2 -- the slug vocabulary every downstream mention resolves against.
 */
class SyllabusConceptsTest extends TestCase
{
    #[Test]
    public function it_declares_every_slug_the_lessons_introduce(): void
    {
        $this->assertSame(
            ['coefficient', 'equation', 'expression', 'inverse-operation', 'variable'],
            $this->concepts()->declared(),
        );
    }

    #[Test]
    public function a_slug_taught_twice_is_introduced_in_the_earlier_lesson(): void
    {
        $concepts = $this->concepts();

        $this->assertSame([1, 2], $concepts->taughtIn('variable'));
        $this->assertSame(1, $concepts->introducedIn('variable'));
        $this->assertSame(2, $concepts->introducedIn('inverse-operation'));
    }

    #[Test]
    public function an_undeclared_slug_does_not_resolve(): void
    {
        $this->expectException(UndeclaredConcept::class);

        $this->concepts()->introducedIn('quadratic-formula');
    }

    #[Test]
    public function asserting_a_batch_names_every_offender_at_once(): void
    {
        try {
            $this->concepts()->assertDeclared(['variable', 'quadratic-formula', 'matrix', 'matrix']);

            $this->fail('Expected an UndeclaredConcept.');
        } catch (UndeclaredConcept $e) {
            $this->assertStringContainsString('quadratic-formula', $e->getMessage());
            $this->assertStringContainsString('matrix', $e->getMessage());
            $this->assertStringNotContainsString('[variable,', $e->getMessage());
        }
    }

    #[Test]
    public function the_final_exam_draws_only_on_declared_concepts(): void
    {
        $syllabus = CourseSyllabusFactory::exampleSyllabus();

        // The self-consistency the generation path enforces (spec 4.5.2's
        // done-condition, checked here against the fixture it uses).
        $this->concepts()->assertDeclared($syllabus['final_exam_concepts']);

        $this->addToAssertionCount(1);
    }

    private function concepts(): SyllabusConcepts
    {
        return SyllabusConcepts::from(CourseSyllabusFactory::exampleSyllabus());
    }
}
