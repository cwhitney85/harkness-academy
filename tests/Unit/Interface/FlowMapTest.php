<?php

namespace Tests\Unit\Interface;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.6.1 -- "every route in `routes/` appears as at least one edge".
 *
 * The flow map in docs/specs/08-interface.md is the spec; `routes/` is the
 * code. This test reads both and holds them to each other, so a route added
 * without an edge fails here rather than becoming a screen nobody can reach.
 *
 * Routes are read from the files rather than from the router, because 8.6.1
 * scopes itself to `routes/` and the router also carries Fortify's and the
 * framework's, which are not ours to draw.
 *
 * One direction only, on purpose. Five of the twelve screens have no routes
 * yet (8.7, 8.10.2) and the map names their URLs *before* their controllers
 * are written, so the reverse check -- every named URL exists -- cannot pass
 * until 8.7 lands and is not what 8.6.1 asks for.
 */
class FlowMapTest extends TestCase
{
    #[Test]
    public function every_route_declared_in_routes_appears_in_the_flow_map(): void
    {
        $map = $this->flowMap();
        $declared = $this->routesDeclared();

        $this->assertNotEmpty($map, 'Section 8.6 could not be read from docs/specs/08-interface.md.');

        // Anti-vacuity: a parse that stopped finding routes would otherwise
        // pass this test by having nothing to check.
        $this->assertGreaterThan(20, count($declared), 'routes/ could not be read.');

        $missing = array_values(array_filter(
            $declared,
            fn (string $needle): bool => ! str_contains($map, $needle),
        ));

        $this->assertSame(
            [],
            $missing,
            'These routes have no edge in the 8.6 flow map: '.implode(', ', $missing),
        );
    }

    #[Test]
    public function every_route_the_flow_map_draws_is_a_route_that_exists(): void
    {
        foreach ($this->routesDeclared() as $needle) {
            if (! str_contains($needle, '.')) {
                continue;
            }

            $this->assertTrue(
                Router::has($needle),
                "The flow map is checked against route [{$needle}], which the router does not have.",
            );
        }
    }

    /**
     * Every route name declared in `routes/`, plus the URI of the one route
     * there that has no name.
     *
     * @return list<string>
     */
    private function routesDeclared(): array
    {
        $declared = [];

        foreach (File::files(base_path('routes')) as $file) {
            if ($file->getFilename() === 'console.php') {
                continue;
            }

            $source = $file->getContents();

            preg_match_all("/->name\('([^']+)'\)/", $source, $names);
            preg_match_all("/Route::redirect\('([^']+)'/", $source, $redirects);

            $declared = array_merge($declared, $names[1], $redirects[1]);
        }

        return array_values(array_unique($declared));
    }

    private function flowMap(): string
    {
        $spec = File::get(base_path('docs/specs/08-interface.md'));

        $start = strpos($spec, '## 8.6 Flow map');
        $end = strpos($spec, '## 8.7 ');

        if ($start === false || $end === false || $end < $start) {
            return '';
        }

        return substr($spec, $start, $end - $start);
    }
}
