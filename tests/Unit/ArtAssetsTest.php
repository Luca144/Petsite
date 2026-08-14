<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use League\Plates\Engine;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the artist's delivered artwork is present and actually used.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHY THIS TEST EXISTS: a broken image is a silent failure. The HTML can look
 * perfectly correct while the file it points at is missing, renamed, or was never
 * committed — and nobody notices until a visitor sees an empty box. So this test
 * checks BOTH halves of the link: that each artwork file exists on disk, and that
 * the template which is supposed to show it really points at that path.
 *
 * If you add new artwork, add it here too. It is a cheap habit that catches a
 * whole class of embarrassing mistakes.
 */
final class ArtAssetsTest extends TestCase
{
    /**
     * The artwork the site depends on: the path used in the HTML, mapped to the
     * file it must resolve to inside /public.
     */
    private const REQUIRED_ARTWORK = [
        '/assets/art/favicon.png',
        '/assets/art/logo-large.png',
        '/assets/art/background.webp',
        '/assets/art/not-found.gif',
    ];

    private function publicPath(string $webPath): string
    {
        return dirname(__DIR__, 2) . '/public' . $webPath;
    }

    private function render(string $template): string
    {
        $templates = new Engine(dirname(__DIR__, 2) . '/templates');

        return $templates->render($template, [
            'appName' => 'Felkyo Creatures',
            'message' => 'We couldn\'t find that page.',
        ]);
    }

    public function testEveryRequiredArtworkFileExists(): void
    {
        foreach (self::REQUIRED_ARTWORK as $webPath) {
            $this->assertFileExists(
                $this->publicPath($webPath),
                "The site references {$webPath}, but that file is missing from /public."
            );
        }
    }

    /**
     * The masthead shows the wide painted banner on every screen size — the
     * 2026-08-14 redesign retired the square badge from the layout (it filled
     * half a phone screen on every page; the banner scales to a slim strip).
     */
    public function testTheMastheadUsesTheWideBanner(): void
    {
        $html = $this->render('pages/hello');

        $this->assertStringContainsString('/assets/art/logo-large.png', $html);
    }

    /**
     * The page field is the artist's painted starfield, applied by the theme
     * stylesheet. Both halves are checked: the file exists (above) and the
     * stylesheet really points at it — a broken background fails silently
     * back to a plain dark page, which nobody would report as a bug.
     */
    public function testTheThemePointsAtThePaintedBackground(): void
    {
        $themeCss = file_get_contents(dirname(__DIR__, 2) . '/public/css/theme.css');

        $this->assertStringContainsString('/assets/art/background.webp', $themeCss);
    }

    public function testTheBrowserTabIconIsTheRealFavicon(): void
    {
        $this->assertStringContainsString('/assets/art/favicon.png', $this->render('pages/hello'));
    }

    /**
     * Meaningful images need descriptive alt text (CLAUDE.md accessibility rules).
     * The logo's job is to say the site's name, so that is exactly its alt text.
     */
    public function testTheLogoHasAltTextNamingTheSite(): void
    {
        $this->assertStringContainsString('alt="Felkyo Creatures"', $this->render('pages/hello'));
    }

    public function testTheNotFoundPageShowsItsArtworkWithAltText(): void
    {
        $html = $this->render('pages/not-found');

        $this->assertStringContainsString('/assets/art/not-found.gif', $html);
        // A non-empty alt attribute on that image, so it is described rather than
        // announced as an unlabelled graphic.
        $this->assertMatchesRegularExpression(
            '/not-found\.gif"[^>]*alt="[^"]+"/s',
            $html,
            'The 404 artwork must have descriptive alt text.'
        );
    }
}
