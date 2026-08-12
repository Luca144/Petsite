<?php

declare(strict_types=1);

namespace Felkyo\Design;

/**
 * Works out how readable one colour is on another.
 *
 * @package Felkyo\Design
 *
 * WHAT THIS IS: the accessibility rule from CLAUDE.md section 8, written as code
 * so it can be checked automatically instead of judged by eye. Body text needs a
 * contrast ratio of at least 4.5 against its background; large text needs 3.
 *
 * WHY IT EXISTS RATHER THAN "JUST LOOKING": judging contrast by eye is genuinely
 * hard, and it is hardest for exactly the people making the decision, because a
 * designer with good eyesight on a bright screen sees a pale gold on cream as
 * "delicate" while a reader with low vision on a phone in daylight sees nothing
 * at all. A number does not have that problem. This is also why gold text on
 * parchment is banned in CLAUDE.md — it looks lovely and it scores 1.9.
 *
 * HOW THE MATHS WORKS, briefly, because it looks stranger than it is. Screens
 * store colour values that are deliberately NOT proportional to how bright we
 * perceive them (there is more precision in the darks, where our eyes are fussier).
 * So step one undoes that curve to get real light amounts. Step two weights the
 * three channels by how bright each looks to a human eye — green counts for
 * roughly 72%, red 21%, blue only 7%, which is why yellow text is so much harder
 * to read than blue text of the same "value". Step three compares the two results.
 *
 * The formula is WCAG 2.1's, not ours. It is quoted here so nobody has to trust
 * a magic number, and so the theme editor in M4.2 can reuse exactly this class
 * rather than growing a second, subtly different copy.
 */
final class ColourContrast
{
    /**
     * The WCAG AA threshold for ordinary body text. Anything below this is not a
     * matter of taste — it is text some people cannot read.
     */
    public const MINIMUM_FOR_BODY_TEXT = 4.5;

    /**
     * The ratio between two colours, from 1 (identical) to 21 (black on white).
     * The order of the two arguments does not matter.
     */
    public static function ratio(string $firstHex, string $secondHex): float
    {
        $first = self::relativeLuminance($firstHex);
        $second = self::relativeLuminance($secondHex);

        // The lighter colour always goes on top of the fraction, which is why the
        // caller does not have to remember which way round to pass them.
        $lighter = max($first, $second);
        $darker = min($first, $second);

        // The 0.05 added to both sides represents the small amount of light a real
        // screen reflects even when showing black. Without it, black on black
        // would divide by zero.
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Is this pair readable as body text?
     */
    public static function isReadableAsBodyText(string $textHex, string $backgroundHex): bool
    {
        return self::ratio($textHex, $backgroundHex) >= self::MINIMUM_FOR_BODY_TEXT;
    }

    /**
     * How much light a colour actually emits, on a scale of 0 (black) to 1
     * (white), adjusted for how a human eye weights red, green and blue.
     */
    private static function relativeLuminance(string $hex): float
    {
        [$red, $green, $blue] = self::toChannels($hex);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * Turn "#F1E4C3" into three light amounts between 0 and 1, undoing the
     * screen's brightness curve as described in the class docblock.
     *
     * @return array{float, float, float}
     */
    private static function toChannels(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new \InvalidArgumentException(
                "Expected a six-digit hex colour like #F1E4C3, but got \"{$hex}\"."
            );
        }

        $channels = [];
        foreach ([0, 2, 4] as $position) {
            $value = hexdec(substr($hex, $position, 2)) / 255;

            // Very dark values use a simple straight line; everything else uses
            // the curve. Both halves come straight from the WCAG definition.
            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return $channels;
    }
}
