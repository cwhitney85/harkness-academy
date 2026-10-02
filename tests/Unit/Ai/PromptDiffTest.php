<?php

namespace Tests\Unit\Ai;

use App\Ai\Prompts\PromptDiff;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Spec 15.2.4 -- the line diff behind "any two versions can be diffed". */
class PromptDiffTest extends TestCase
{
    #[Test]
    public function an_edited_line_reads_as_a_removal_and_an_addition(): void
    {
        $lines = PromptDiff::between(
            "Explain it.\nUnder 200 words.\n",
            "Explain it.\nUnder 100 words.\n",
        );

        $this->assertSame([
            ['marker' => 'keep', 'text' => 'Explain it.'],
            ['marker' => 'remove', 'text' => 'Under 200 words.'],
            ['marker' => 'add', 'text' => 'Under 100 words.'],
        ], $lines);
    }

    #[Test]
    public function an_inserted_line_leaves_its_neighbours_alone(): void
    {
        $lines = PromptDiff::between("One.\nThree.\n", "One.\nTwo.\nThree.\n");

        $this->assertSame([
            ['marker' => 'keep', 'text' => 'One.'],
            ['marker' => 'add', 'text' => 'Two.'],
            ['marker' => 'keep', 'text' => 'Three.'],
        ], $lines);
    }

    #[Test]
    public function identical_bodies_are_all_keeps(): void
    {
        $lines = PromptDiff::between("Same.\nText.\n", "Same.\nText.\n");

        $this->assertSame(['keep', 'keep'], array_column($lines, 'marker'));
    }

    /**
     * The postcondition, on a body that moves a paragraph and rewrites
     * another: reading one side of the diff must give back that side's body.
     */
    #[Test]
    public function each_side_of_the_diff_reads_back_as_its_own_body(): void
    {
        $from = "Alpha\n\nBravo\nCharlie\n\nDelta\n";
        $to = "Charlie\n\nAlpha\nBravo revised\n\nDelta\n";

        $lines = PromptDiff::between($from, $to);

        $this->assertSame(rtrim($from, "\n"), $this->side($lines, 'remove'));
        $this->assertSame(rtrim($to, "\n"), $this->side($lines, 'add'));
    }

    /**
     * @param  list<array{marker: string, text: string}>  $lines
     */
    private function side(array $lines, string $only): string
    {
        return implode("\n", array_column(
            array_filter($lines, fn (array $line): bool => in_array($line['marker'], ['keep', $only], true)),
            'text',
        ));
    }
}
