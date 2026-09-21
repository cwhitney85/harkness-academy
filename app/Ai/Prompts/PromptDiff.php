<?php

namespace App\Ai\Prompts;

/**
 * A line diff between two prompt bodies (spec 15.2.4).
 *
 * Written here rather than taken from a package: the only diff library in the
 * tree is `sebastian/diff`, which arrives with PHPUnit and so is not there in
 * production. A prompt body is tens of lines of prose, so the quadratic table
 * an exact longest-common-subsequence needs is cheaper than the dependency.
 */
final class PromptDiff
{
    /**
     * Post: the `keep` and `remove` lines, read in order, are `$from`; the
     *       `keep` and `add` lines are `$to`.
     *
     * @return list<array{marker: 'keep'|'add'|'remove', text: string}>
     */
    public static function between(string $from, string $to): array
    {
        $before = explode("\n", rtrim($from, "\n"));
        $after = explode("\n", rtrim($to, "\n"));

        $lengths = self::commonSubsequenceLengths($before, $after);

        $lines = [];
        $i = 0;
        $j = 0;

        while ($i < count($before) && $j < count($after)) {
            if ($before[$i] === $after[$j]) {
                $lines[] = ['marker' => 'keep', 'text' => $before[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $lines[] = ['marker' => 'remove', 'text' => $before[$i]];
                $i++;
            } else {
                $lines[] = ['marker' => 'add', 'text' => $after[$j]];
                $j++;
            }
        }

        while ($i < count($before)) {
            $lines[] = ['marker' => 'remove', 'text' => $before[$i++]];
        }

        while ($j < count($after)) {
            $lines[] = ['marker' => 'add', 'text' => $after[$j++]];
        }

        return $lines;
    }

    /**
     * `$lengths[$i][$j]` is the length of the longest common subsequence of
     * `$before` from `$i` and `$after` from `$j`, which is what the walk above
     * reads to choose a side at each disagreement.
     *
     * Indexed by position in each body, so it is read by integer index rather
     * than iterated -- `array<int, ...>` and not `list`.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     * @return array<int, array<int, int>>
     */
    private static function commonSubsequenceLengths(array $before, array $after): array
    {
        $n = count($before);
        $m = count($after);

        $lengths = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $before[$i] === $after[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        return $lengths;
    }
}
