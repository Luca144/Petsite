<?php

declare(strict_types=1);

namespace Felkyo\Themes;

use Felkyo\Design\ColourContrast;

/**
 * Which colours a theme may change, and the readability rules every theme
 * must pass (M4.1, M4.2).
 *
 * @package Felkyo\Themes
 *
 * A theme is a set of overrides for the colour tokens in theme.css. Only
 * the tokens listed in EDITABLE can be overridden — an allow-list, so a
 * theme can never inject anything but a colour into the page.
 *
 * THE RULES are the actual pairings the stylesheets use (text colour on the
 * surface it sits on). Each must reach WCAG AA, 4.5:1. They are checked:
 *   - live in the panel while colours are picked (theme-editor.js — the same
 *     list is handed to the page as data, so the two can't disagree);
 *   - on the server when a theme is published, which refuses a failing one.
 * The default Lamplight palette passes every rule with room to spare.
 */
final class ThemeTokens
{
    /** token => [what it's called in the panel, the site's own value] */
    public const EDITABLE = [
        'parchment' => ['Page surface', '#F1E4C3'],
        'panel' => ['Cards and boxes', '#E7D4A8'],
        'plum' => ['Body text and dark panels', '#3D2B4F'],
        'plum-deep' => ['Darkest panels', '#2A1C3A'],
        'purple' => ['Links and buttons', '#7B3FA0'],
        'ink-soft' => ['Quieter text', '#5A4568'],
        'gold' => ['Borders and sparkle', '#C9A227'],
        'gold-lit' => ['Glow, and text on dark panels', '#E8C95A'],
        'border' => ['Lines and outlines', '#B89A6A'],
    ];

    /** [text token, background token, where a player sees it] */
    public const RULES = [
        ['plum', 'parchment', 'body text on the page'],
        ['plum', 'panel', 'text inside cards'],
        ['ink-soft', 'parchment', 'quiet text on the page'],
        ['ink-soft', 'panel', 'quiet text inside cards'],
        ['purple', 'parchment', 'links on the page'],
        ['purple', 'panel', 'links inside cards'],
        ['parchment', 'purple', 'button labels'],
        ['gold-lit', 'plum', 'gold text on dark panels'],
        ['parchment', 'plum-deep', 'light text on the darkest panels'],
    ];

    public const MINIMUM_RATIO = 4.5;

    /**
     * Keep only allowed tokens with a valid #RRGGBB value.
     *
     * @param array<string, mixed> $tokens
     * @return array<string, string>
     */
    public static function clean(array $tokens): array
    {
        $clean = [];
        foreach (self::EDITABLE as $token => $_) {
            $value = $tokens[$token] ?? null;
            if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                $clean[$token] = strtoupper($value);
            }
        }

        return $clean;
    }

    /**
     * Every rule a full palette (overrides on top of the defaults) fails, in
     * plain words, or an empty list when it is readable everywhere.
     *
     * @param array<string, string> $overrides
     * @return string[]
     */
    public static function problems(array $overrides): array
    {
        $palette = self::paletteWith($overrides);
        $problems = [];
        foreach (self::RULES as [$text, $background, $where]) {
            $ratio = ColourContrast::ratio($palette[$text], $palette[$background]);
            if ($ratio < self::MINIMUM_RATIO) {
                $problems[] = sprintf('%s is hard to read (%.1f:1 — needs %.1f:1).', ucfirst($where), $ratio, self::MINIMUM_RATIO);
            }
        }

        return $problems;
    }

    /** @return array<string, string> the full palette: defaults, then overrides */
    public static function paletteWith(array $overrides): array
    {
        $palette = [];
        foreach (self::EDITABLE as $token => [, $default]) {
            $palette[$token] = $overrides[$token] ?? $default;
        }

        return $palette;
    }
}
