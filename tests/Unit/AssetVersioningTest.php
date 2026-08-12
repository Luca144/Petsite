<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Http\AssetUrl;
use PHPUnit\Framework\TestCase;

/**
 * Stops a changed stylesheet from being served from a stale browser cache.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHY THIS TEST EXISTS: this caught us out for real. A stylesheet was edited, the
 * site was deployed, and returning visitors kept the old copy — so half the page
 * picked up the new design and half of it did not. Brand-new stylesheets loaded
 * immediately, which made it look like a random rendering fault rather than a
 * caching one.
 *
 * The fix is that a changed file gets a changed address. This test proves the
 * addresses really are versioned, so nobody can quietly remove it while tidying
 * the layout and rediscover the same afternoon of confusion.
 */
final class AssetVersioningTest extends TestCase
{
    public function testAStylesheetAddressCarriesAVersion(): void
    {
        $url = AssetUrl::versioned('/css/theme.css');

        $this->assertMatchesRegularExpression('~^/css/theme\.css\?v=\d+$~', $url);
    }

    public function testTheVersionIsTheFilesModificationTime(): void
    {
        // Tied to the file itself, so it changes exactly when the file does and
        // never otherwise — no cache to clear, nothing to remember.
        $expected = (string) filemtime(dirname(__DIR__, 2) . '/public/css/theme.css');

        $this->assertStringEndsWith('?v=' . $expected, AssetUrl::versioned('/css/theme.css'));
    }

    public function testTwoDifferentFilesGetDifferentVersions(): void
    {
        // If every file shared one version, changing one stylesheet would make
        // every browser re-fetch all of them — and, worse, a version that did not
        // move when a file changed would put us straight back where we started.
        $theme = AssetUrl::versioned('/css/theme.css');
        $economy = AssetUrl::versioned('/css/economy.css');

        $this->assertNotSame($theme, $economy);
    }

    public function testAMissingFileDoesNotBringTheWholePageDown(): void
    {
        // A missing stylesheet already shows up as an unstyled page. Throwing here
        // would turn that into a blank one.
        $this->assertSame('/css/not-here.css?v=0', AssetUrl::versioned('/css/not-here.css'));
    }

    public function testEveryStylesheetInTheLayoutIsVersioned(): void
    {
        // The point of failure is the layout, not the helper: somebody adds a
        // stylesheet and writes a plain href out of habit. This reads the real
        // template and fails on any link that is not versioned.
        $layout = file_get_contents(dirname(__DIR__, 2) . '/templates/layout.php');

        preg_match_all('~<link rel="stylesheet" href="([^"]*)"~', $layout, $matches);

        // Only our own files. The web-font link points at Google's servers, which
        // we neither host nor version — their address is their business.
        $ourStylesheets = array_filter(
            $matches[1],
            static fn (string $href): bool => !str_starts_with($href, 'http')
        );

        $this->assertNotEmpty($ourStylesheets, 'No local stylesheets found in the layout at all.');

        foreach ($ourStylesheets as $href) {
            $this->assertStringContainsString(
                'AssetUrl::versioned',
                $href,
                "A stylesheet is linked without a version: {$href}. Returning visitors will keep the old copy."
            );
        }
    }
}
