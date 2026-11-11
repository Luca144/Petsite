<?php

declare(strict_types=1);

namespace Felkyo\Themes;

/**
 * The non-colour half of a theme: fonts, corners, frames, glow and the
 * little symbols drifting down the page.
 *
 * @package Felkyo\Themes
 *
 * EVERY CHOICE IS FROM A LIST. The artist picks among named options; the
 * CSS each option produces is written here, by us. Nothing typed in the
 * panel ever becomes CSS — which is what makes it safe for css() to be
 * printed raw into the page, and what keeps every option readable and
 * working on a phone.
 *
 * The FIRST entry of each list is the site's own look. A choice equal to it
 * is simply not stored, so a theme keeps following the site for everything
 * it didn't change.
 *
 * HOW TO ADD AN OPTION: add one line to the right list below. A font also
 * needs its Google Fonts family string (the third value) so the page can
 * load it. Nothing else changes — the editor and the page read these lists.
 */
final class ThemeStyle
{
    /** key => [what the artist sees, CSS font stack, Google Fonts family or null if already loaded] */
    public const HEADING_FONTS = [
        'fraunces' => ['Fraunces — the site\'s own', null, null],
        'pixelify' => ['Pixelify Sans — pixel letters', "'Pixelify Sans', 'Courier New', monospace", 'Pixelify+Sans:wght@400;700'],
        'grandstander' => ['Grandstander — round and bouncy', "'Grandstander', 'Comic Sans MS', cursive", 'Grandstander:wght@500;800'],
        'cinzel' => ['Cinzel Decorative — storybook', "'Cinzel Decorative', Georgia, serif", 'Cinzel+Decorative:wght@400;700'],
        'lora' => ['Lora — gentle serif', "'Lora', Georgia, serif", 'Lora:wght@500;700'],
        'caveat' => ['Caveat Brush — handwritten', "'Caveat Brush', 'Segoe Print', cursive", 'Caveat+Brush'],
    ];

    /** Body fonts are for reading paragraphs, so only clearly readable ones. */
    public const BODY_FONTS = [
        'nunito' => ['Nunito — the site\'s own', null, null],
        'atkinson' => ['Atkinson Hyperlegible — the clearest', "'Atkinson Hyperlegible', system-ui, sans-serif", 'Atkinson+Hyperlegible:wght@400;700'],
        'andika' => ['Andika — made for new readers', "'Andika', system-ui, sans-serif", 'Andika:wght@400;700'],
        'quicksand' => ['Quicksand — soft and round', "'Quicksand', system-ui, sans-serif", 'Quicksand:wght@500;700'],
        'lora' => ['Lora — a storybook serif', "'Lora', Georgia, serif", 'Lora:wght@400;700'],
    ];

    /** key => [label, small / medium / large corner radius in px, or null for the site's own] */
    public const CORNERS = [
        'soft' => ['Softly rounded — the site\'s own', null],
        'round' => ['Very round, like pebbles', [14, 26, 34]],
        'square' => ['Nearly square, like old paper', [2, 4, 6]],
    ];

    /** key => [label, CSS border-style for the frames around boxes] */
    public const FRAMES = [
        'plain' => ['A plain line — the site\'s own', null],
        'stitched' => ['Stitched (dashes)', 'dashed'],
        'dotted' => ['Dotted', 'dotted'],
    ];

    /** key => [label, gold glow, panel shadow] */
    public const GLOWS = [
        'soft' => ['A soft glow — the site\'s own', null, null],
        'bright' => ['A bright, magical glow', '0 0 34px rgba(232, 201, 90, 0.85)', '0 40px 90px -30px rgba(61, 43, 79, 0.6)'],
        'none' => ['No glow — calm and flat', 'none', 'none'],
    ];

    /** key => [label, the two symbols that drift down the page] */
    public const MOTES = [
        'stars' => ['Stars — the site\'s own', ['&#10022;', '&#10023;']],
        'hearts' => ['Hearts', ['&#9829;', '&#9825;']],
        'snow' => ['Snowflakes', ['&#10052;', '&#10053;']],
        'petals' => ['Petals', ['&#10048;', '&#10047;']],
        'moons' => ['Moons and sparkles', ['&#9790;', '&#10038;']],
        'none' => ['Nothing drifting', []],
    ];

    /** Which list each stored choice is checked against. */
    private const LISTS = [
        'heading_font' => self::HEADING_FONTS,
        'body_font' => self::BODY_FONTS,
        'corners' => self::CORNERS,
        'frames' => self::FRAMES,
        'glow' => self::GLOWS,
        'motes' => self::MOTES,
    ];

    /** What the panel calls each choice. */
    public const NAMES = [
        'heading_font' => 'Heading letters',
        'body_font' => 'Reading letters',
        'corners' => 'Corners',
        'frames' => 'Frames around boxes',
        'glow' => 'Glow',
        'motes' => 'Drifting down the page',
    ];

    /** @param array<string, string> $choices already cleaned */
    public function __construct(public readonly array $choices = [])
    {
    }

    /**
     * Keep only known choices with known values, and drop any that equal the
     * site's own (the first entry). Anything else that arrives is ignored.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromChoices(array $raw): self
    {
        $clean = [];
        foreach (self::LISTS as $name => $options) {
            $value = $raw[$name] ?? null;
            if (is_string($value) && isset($options[$value]) && $value !== array_key_first($options)) {
                $clean[$name] = $value;
            }
        }

        return new self($clean);
    }

    /** @return array<string, array<string, array>> name => the option list, for the editor */
    public static function lists(): array
    {
        return self::LISTS;
    }

    /** The chosen value for one choice, or the site's own. */
    public function chosen(string $name): string
    {
        return $this->choices[$name] ?? (string) array_key_first(self::LISTS[$name]);
    }

    /**
     * The custom properties this style sets. Every value comes from the
     * constants above, never from what was typed.
     *
     * @return string[] CSS declarations like "--font-display: ...;"
     */
    public function declarations(): array
    {
        $out = [];
        if (($stack = self::HEADING_FONTS[$this->chosen('heading_font')][1]) !== null) {
            $out[] = '--font-display: ' . $stack . ';';
        }
        if (($stack = self::BODY_FONTS[$this->chosen('body_font')][1]) !== null) {
            $out[] = '--font-body: ' . $stack . ';';
        }
        if (($radii = self::CORNERS[$this->chosen('corners')][1]) !== null) {
            $out[] = sprintf('--radius-sm: %dpx; --radius-md: %dpx; --radius-lg: %dpx;', ...$radii);
        }
        if (($frame = self::FRAMES[$this->chosen('frames')][1]) !== null) {
            $out[] = '--frame-style: ' . $frame . ';';
        }
        [, $glow, $shadow] = self::GLOWS[$this->chosen('glow')];
        if ($glow !== null) {
            $out[] = '--glow-gold: ' . $glow . '; --shadow-panel: ' . $shadow . ';';
        }

        return $out;
    }

    /** The Google Fonts families this style needs loaded (none for the site's own). @return string[] */
    public function fontFamilies(): array
    {
        return array_values(array_filter([
            self::HEADING_FONTS[$this->chosen('heading_font')][2],
            self::BODY_FONTS[$this->chosen('body_font')][2],
        ]));
    }

    /** @return string[] the drifting symbols, as HTML entities (safe to print raw) */
    public function motes(): array
    {
        return self::MOTES[$this->chosen('motes')][1];
    }
}
