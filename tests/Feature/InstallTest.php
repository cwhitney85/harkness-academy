<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 7.1 — the manifest, the icons it names, and the install page.
 *
 * The done-condition is a real phone, which no test can stand in for. What is
 * checkable here is everything a phone would fail on silently: a manifest that
 * does not parse, an icon that is not the size it claims, a colour that has
 * drifted from the token it was taken from, or a page nobody can reach.
 */
class InstallTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The manifest's colours are the sRGB of two of 8.3.1's ten tokens —
     * `--rail-green` for the chrome the app opens in and `--ground` for the
     * splash behind it, which is also what app.blade.php paints `html`. A
     * manifest has no stylesheet, so the values are converted by hand and this
     * is what keeps the two in step: change the token, this fails, convert the
     * new value.
     */
    private const COLOURS = [
        'theme_color' => ['#051D0E', '--rail-green: oklch(0.205 0.042 155)'],
        'background_color' => ['#F4F2EB', '--ground: oklch(0.962 0.01 95)'],
    ];

    #[Test]
    public function the_manifest_carries_every_field_spec_7_1_names(): void
    {
        $manifest = $this->manifest();

        $this->assertSame('Harkness Academy', $manifest['name']);
        $this->assertSame('Harkness Academy', $manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);

        $css = File::get(base_path('resources/css/app.css'));

        foreach (self::COLOURS as $field => [$srgb, $token]) {
            $this->assertSame($srgb, $manifest[$field]);
            $this->assertStringContainsString(
                $token,
                $css,
                "The manifest's {$field} is the sRGB of `{$token}`, which app.css no longer declares.",
            );
        }
    }

    #[Test]
    public function every_icon_the_manifest_names_exists_at_the_size_it_claims(): void
    {
        $purposes = [];

        foreach ($this->manifest()['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));

            $this->assertFileExists($path);

            [$width, $height] = getimagesize($path);
            $this->assertSame(
                $icon['sizes'],
                "{$width}x{$height}",
                "{$icon['src']} is {$width}x{$height}, not the {$icon['sizes']} the manifest claims.",
            );

            $purposes[$icon['purpose']][] = $icon['sizes'];
        }

        $this->assertSame(['192x192', '512x512'], $purposes['any'] ?? []);
        $this->assertSame(['512x512'], $purposes['maskable'] ?? []);
    }

    /**
     * iOS reads none of the manifest before 16.4 and would open the app with
     * browser chrome and name the icon after whichever page was on screen, so
     * the two apple tags carry those two facts instead.
     */
    #[Test]
    public function the_page_links_the_manifest_and_tells_ios_what_the_manifest_says(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
        $response->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false);
        $response->assertSee('<meta name="apple-mobile-web-app-title" content="Harkness Academy">', false);
    }

    #[Test]
    public function the_install_page_renders_for_a_signed_in_student(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('install'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('install'));
    }

    #[Test]
    public function the_install_page_is_behind_the_auth_boundary(): void
    {
        $this->get(route('install'))->assertRedirect(route('login'));
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $manifest = json_decode(File::get(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest, 'public/manifest.webmanifest is not valid JSON.');

        return $manifest;
    }
}
