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

    public function testEveryStylesheetAndScriptIsVersioned(): void
    {
        // The point of failure is the template, not the helper: somebody adds a
        // stylesheet and writes a plain href out of habit. This reads the real
        // templates and fails on any link that is not versioned.
        //
        // EVERY TEMPLATE, not one named file. It used to read layout.php alone —
        // and then the whole <head> moved into partials/head.php, at which point
        // the test found no stylesheets and could no longer fail for its real
        // reason. Scanning the directory means moving markup around cannot
        // quietly switch this check off, and a stylesheet added to some future
        // page template is covered too.
        //
        // Scripts are included as well: a stale cached script is the same bug as a
        // stale cached stylesheet, and item-finder.js is linked from two pages.
        $templates = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/templates')
        );

        $unversioned = [];
        $found = 0;

        foreach ($templates as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $markup = (string) file_get_contents($file->getPathname());
            preg_match_all(
                '~(?:<link rel="stylesheet" href|<script src)="([^"]*)"~',
                $markup,
                $matches
            );

            foreach ($matches[1] as $href) {
                // Only our own files. The web-font link points at Google's servers,
                // which we neither host nor version — their address is their business.
                if (str_starts_with($href, 'http')) {
                    continue;
                }

                $found++;
                if (!str_contains($href, 'AssetUrl::versioned')) {
                    $unversioned[] = $file->getFilename() . ': ' . $href;
                }
            }
        }

        $this->assertGreaterThan(0, $found, 'No local stylesheets or scripts found in any template.');
        $this->assertSame(
            [],
            $unversioned,
            "Linked without a version, so returning visitors keep the old copy:\n"
            . implode("\n", $unversioned)
        );
    }
}
