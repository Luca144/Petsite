<?php

declare(strict_types=1);

namespace Felkyo\Look;

/**
 * What the fine-tuning CSS box may and may not contain.
 *
 * @package Felkyo\Look
 *
 * WHY THESE RULES. The CSS is printed into every page inside a <style> tag,
 * for every player — most of them children. CSS can't run scripts in a
 * modern browser, but it can still do harm, so:
 *
 *   - no "<" at all: it is the only way to close the <style> tag early and
 *     start writing HTML (and real CSS never needs it);
 *   - no @import, and pictures/fonts only from THIS site (/art/ or /assets/):
 *     an outside address would make every player's browser report to
 *     someone else's server — a tracking pixel aimed at children;
 *   - no old browser tricks that once ran code (expression(), behavior:,
 *     -moz-binding, javascript:);
 *   - a size cap, so a paste accident can't add megabytes to every page.
 *
 * Checked when saving AND again when printing (SiteLook::fromSettings), so a
 * row edited by hand in the database still can't get through.
 */
final class CustomCssRules
{
    public const MAX_LENGTH = 20000;

    /** Lower-cased fragments that are refused anywhere, with the reason to show. */
    private const FORBIDDEN = [
        '<' => 'The "<" character isn\'t allowed (CSS never needs it).',
        '@import' => '@import isn\'t allowed — put the rules in the box directly.',
        'expression(' => 'expression() isn\'t allowed.',
        'javascript:' => 'javascript: isn\'t allowed.',
        'behavior' => '"behavior" isn\'t allowed.',
        '-moz-binding' => '-moz-binding isn\'t allowed.',
        // A backslash escape could spell "url(" or "@import" in disguise and
        // slip past every check above. Paste the character itself instead.
        '\\' => 'Backslash escapes aren\'t allowed — paste the character itself instead (like ✦).',
        // image-set() loads pictures from plain strings, without url().
        'image-set' => 'image-set() isn\'t allowed — use url(/art/...) instead.',
    ];

    /** A plain sentence saying what's wrong, or null if the CSS may be used. */
    public static function problemWith(string $css): ?string
    {
        if (mb_strlen($css) > self::MAX_LENGTH) {
            return sprintf('That\'s more than %s characters — please trim it.', number_format(self::MAX_LENGTH));
        }
        $lower = strtolower($css);
        foreach (self::FORBIDDEN as $fragment => $reason) {
            if (str_contains($lower, $fragment)) {
                return $reason;
            }
        }

        // Every url(...) must point at a picture or font on this site.
        preg_match_all('/url\s*\(\s*([^)]*)\)/i', $css, $matches);
        foreach ($matches[1] as $target) {
            $address = trim($target, " \t\n\r'\"");
            if (preg_match('#^/(art|assets)/[A-Za-z0-9._/-]+$#', $address) !== 1) {
                return sprintf('Pictures must come from this site (an address starting /art/ or /assets/), not "%s".', mb_substr($address, 0, 60));
            }
        }

        return null;
    }
}
