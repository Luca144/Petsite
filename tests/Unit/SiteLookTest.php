<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Look\CustomCssRules;
use Felkyo\Look\SiteLook;
use Felkyo\Themes\ThemeStyle;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the site look's safety rules and its section ordering.
 *
 * @package Felkyo\Tests\Unit
 *
 * The fine-tuning CSS is printed raw into every page, so each way out of
 * the rules gets its own refusal test. The ordering tests make sure a saved
 * order can never lose a section or invent one.
 */
final class SiteLookTest extends TestCase
{
    public function testOrdinaryCssIsAllowed(): void
    {
        self::assertNull(CustomCssRules::problemWith('.shop .btn { padding: 1rem; color: var(--purple); }'));
        self::assertNull(CustomCssRules::problemWith('body { background-image: url("/art/abc123.webp"); }'));
    }

    public function testCssThatClosesTheStyleTagIsRefused(): void
    {
        self::assertNotNull(CustomCssRules::problemWith('p{}</style><script>alert(1)</script>'));
    }

    public function testPicturesFromOtherSitesAreRefused(): void
    {
        self::assertNotNull(CustomCssRules::problemWith('body{background:url(https://tracker.example/p.gif)}'));
        self::assertNotNull(CustomCssRules::problemWith('body{background:url(//tracker.example/p.gif)}'));
        self::assertNotNull(CustomCssRules::problemWith('body{background:image-set("https://tracker.example/p.gif" 1x)}'));
    }

    public function testAnUnclosedUrlIsRefused(): void
    {
        // With no ")" a browser reads the address up to the end of the file —
        // so this WOULD load the tracker, and the closed-url check never sees it.
        self::assertNotNull(CustomCssRules::problemWith('body{background:url(https://tracker.example/p.gif'));
        self::assertNotNull(CustomCssRules::problemWith("body{background:url(/art/ok.webp)}\np{background:URL( https://tracker.example/p.gif"));
    }

    public function testImportAndDisguisesAreRefused(): void
    {
        self::assertNotNull(CustomCssRules::problemWith('@import "https://elsewhere.example/x.css";'));
        self::assertNotNull(CustomCssRules::problemWith('@IMPORT "x.css";'));
        // A backslash escape could spell url( in disguise.
        self::assertNotNull(CustomCssRules::problemWith('body{background:u\\72l(https://tracker.example/p.gif)}'));
        self::assertNotNull(CustomCssRules::problemWith('p { width: expression(alert(1)); }'));
    }

    public function testOverlongCssIsRefused(): void
    {
        self::assertNotNull(CustomCssRules::problemWith(str_repeat('a', CustomCssRules::MAX_LENGTH + 1)));
    }

    public function testASavedOrderNeverLosesOrInventsASection(): void
    {
        $order = SiteLook::completeOrder('profile', ['company', 'made-up', 'company']);

        self::assertSame(['company', 'about', 'creatures'], $order);
    }

    public function testMovingPastTheEndChangesNothing(): void
    {
        $order = ['about', 'creatures', 'company'];

        self::assertSame($order, SiteLook::moved($order, 'about', -1));
        self::assertSame($order, SiteLook::moved($order, 'company', 1));
        self::assertSame(['creatures', 'about', 'company'], SiteLook::moved($order, 'creatures', -1));
    }

    public function testThemeStyleKeepsOnlyKnownChoices(): void
    {
        $style = ThemeStyle::fromChoices([
            'heading_font' => 'pixelify',
            'body_font' => 'nunito',              // the site's own — not stored
            'corners' => 'not-a-choice',          // unknown — dropped
            'glow' => '0 0 9px red; } body {',    // typed CSS — dropped
            'something_else' => 'x',
        ]);

        self::assertSame(['heading_font' => 'pixelify'], $style->choices);
    }
}
