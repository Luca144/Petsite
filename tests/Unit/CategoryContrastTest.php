<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Design\ColourContrast;
use PHPUnit\Framework\TestCase;

/**
 * Stops an unreadable colour from ever reaching the site.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHAT THIS DOES: it opens the real theme.css, reads every colour token out of
 * it, and checks that text can actually be read on each of the item category
 * tints. If somebody nudges a colour because it looked prettier, this fails and
 * says by how much.
 *
 * WHY IT READS THE CSS FILE rather than a copied list: a copied list would drift.
 * Somebody would change the stylesheet, the test would keep checking the old
 * values, and it would pass while the site was broken — which is worse than
 * having no test, because it would say everything was fine.
 *
 * This is the small ancestor of the theme editor's live contrast checking in
 * M4.2. When that arrives it uses the same ColourContrast class, so there is one
 * definition of "readable" on the whole site rather than two that disagree.
 */
final class CategoryContrastTest extends TestCase
{
    /** @var array<string, string> Every "--token: #hex" pair found in theme.css. */
    private array $tokens;

    protected function setUp(): void
    {
        $themeFile = dirname(__DIR__, 2) . '/public/css/theme.css';
        $css = file_get_contents($themeFile);

        $this->assertNotFalse($css, "Could not read the theme file at {$themeFile}.");

        // Pull out every "--some-name: #AABBCC" declaration. Tokens defined as
        // var(...) aliases rather than literal colours are simply not matched,
        // which is what we want — they resolve to another token we already check.
        preg_match_all('/(--[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{6})\s*;/i', $css, $matches, PREG_SET_ORDER);

        $this->tokens = [];
        foreach ($matches as $match) {
            $this->tokens[$match[1]] = $match[2];
        }
    }

    public function testEveryCategoryTintCanCarryReadableText(): void
    {
        $ink = $this->tokens['--plum'];

        $categoryTints = array_filter(
            $this->tokens,
            static fn (string $token): bool => str_starts_with($token, '--category-'),
            ARRAY_FILTER_USE_KEY
        );

        // If the tints ever vanish from the stylesheet, an empty loop would pass
        // silently and prove nothing at all.
        $this->assertNotEmpty($categoryTints, 'No --category-* colours found in theme.css.');

        foreach ($categoryTints as $token => $hex) {
            $ratio = ColourContrast::ratio($ink, $hex);

            $this->assertGreaterThanOrEqual(
                ColourContrast::MINIMUM_FOR_BODY_TEXT,
                $ratio,
                sprintf(
                    '%s (%s) only reaches %.2f:1 against the ink colour, and text needs %.1f:1. '
                    . 'Make the tint lighter until it passes — some players genuinely cannot read it as it stands.',
                    $token,
                    $hex,
                    $ratio,
                    ColourContrast::MINIMUM_FOR_BODY_TEXT
                )
            );
        }
    }

    public function testTheMainReadingSurfaceIsComfortable(): void
    {
        // The parchment panels carry nearly all the site's words, so they get held
        // to a higher standard than the bare minimum.
        $ratio = ColourContrast::ratio($this->tokens['--plum'], $this->tokens['--parchment']);

        $this->assertGreaterThan(7.0, $ratio, 'Plum on parchment should be comfortably readable, not merely legal.');
    }

    public function testTheBannedCombinationIsStillBanned(): void
    {
        // CLAUDE.md forbids gold text on parchment. This test is not protecting
        // the code — nothing uses that pair — it is protecting the RULE, by
        // demonstrating in one line why it exists. Anyone who wonders whether the
        // ban is fussy can read this and see the number.
        $ratio = ColourContrast::ratio($this->tokens['--gold'], $this->tokens['--parchment']);

        $this->assertLessThan(
            ColourContrast::MINIMUM_FOR_BODY_TEXT,
            $ratio,
            'Gold on parchment now passes contrast. If the palette really changed, update CLAUDE.md deliberately.'
        );
    }
}
