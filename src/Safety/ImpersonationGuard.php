<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * Stops a player from taking a name that pretends to be somebody it is not.
 *
 * @package Felkyo\Safety
 *
 * TWO DIFFERENT PROBLEMS, ONE CLASS.
 *
 * The first is pretending to be staff. Someone called "Felkyo Admin" or "moderator"
 * can ask another player for their password, and be believed — because on a site
 * with children on it, "the person from the site said so" is enough. This is one
 * of the oldest tricks there is and it costs nothing to close.
 *
 * The second is pretending to be another PLAYER, by taking a name that looks the
 * same without being the same: "mira" and "rnira" and "m1ra" and "mirа" (that
 * last one has a Cyrillic а in it). To a reader they are one person. Somebody who
 * builds trust as a familiar name and then behaves differently is doing something
 * a report cannot easily describe.
 *
 * BOTH ARE HANDLED BY THE SAME IDEA: reduce the name to a dull skeleton, and
 * compare skeletons. If two names have the same skeleton, they are the same name
 * as far as this site is concerned.
 *
 * HONEST LIMITS. This catches the obvious impersonations and not the clever ones.
 * A person determined to look like somebody else will manage it; what this does
 * is stop it being effortless, and stop it happening by accident.
 */
final class ImpersonationGuard
{
    /**
     * Words that suggest the account speaks for the site. Matched inside the
     * name, not only as the whole name, because "felkyo helper" is exactly as
     * convincing as "felkyo".
     */
    private const AUTHORITY_WORDS = [
        'admin', 'administrator', 'mod', 'moderator', 'staff', 'official',
        'felkyo', 'support', 'helpdesk', 'system', 'owner', 'team',
    ];

    /**
     * Letters that different alphabets draw the same way. Mapped to the Latin
     * letter they resemble so a Cyrillic "а" and a Latin "a" compare equal.
     *
     * This is a short list of the ones that actually matter — the letters common
     * enough in names to be worth imitating. It is not the full Unicode
     * confusables table, which is enormous and would be its own project.
     */
    private const CONFUSABLE_LETTERS = [
        'а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x',
        'у' => 'y', 'і' => 'i', 'ѕ' => 's', 'ј' => 'j', 'ԁ' => 'd', 'ɡ' => 'g',
        'α' => 'a', 'ο' => 'o', 'ρ' => 'p', 'τ' => 't', 'ν' => 'v', 'κ' => 'k',
    ];

    /**
     * Pairs of letters that draw as one other letter. "rn" looks like "m" in most
     * typefaces at ordinary sizes, which is the classic version of this trick.
     */
    private const CONFUSABLE_PAIRS = ['rn' => 'm', 'vv' => 'w', 'cl' => 'd'];

    /**
     * Does this name claim to speak for the site?
     */
    public function claimsAuthority(string $name): bool
    {
        $skeleton = $this->skeletonOf($name);

        foreach (self::AUTHORITY_WORDS as $word) {
            if (str_contains($skeleton, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Would these two names look like the same name to a person reading quickly?
     */
    public function looksLikeTheSameNameAs(string $candidate, string $existing): bool
    {
        return $this->skeletonOf($candidate) === $this->skeletonOf($existing);
    }

    /**
     * The dull form of a name, for comparing.
     *
     * Everything that can vary without changing how a name READS is stripped or
     * flattened: case, invisible characters, digits standing in for letters,
     * spacing and punctuation, alphabets that draw the same shapes, and the letter
     * pairs that imitate a single letter.
     *
     * The result is not meant to be shown to anybody — "Míra_2000" becomes
     * "mirazooo" and that is fine. It exists only to be compared with another one.
     */
    public function skeletonOf(string $name): string
    {
        // The shared normaliser already handles invisible characters, case,
        // digit-for-letter substitutions and separators.
        $skeleton = TextNormaliser::withoutSpacing($name);

        // Letters from other alphabets that draw the same shape are folded FIRST,
        // while they are still themselves. Doing this after the accent-folding
        // below would be too late: that step drops characters it cannot convert,
        // so a Cyrillic "а" would simply vanish and "mirа" would become "mir" —
        // which no longer resembles "mira" at all, and the check would pass.
        $skeleton = strtr($skeleton, self::CONFUSABLE_LETTERS);

        // Accented letters fold to their plain form, so "míra" matches "mira".
        $folded = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $skeleton);
        if (is_string($folded) && $folded !== '') {
            // iconv renders some letters as "'a" or "~n"; keep only the letters
            // and digits it produced.
            $skeleton = strtolower(preg_replace('/[^a-z0-9]/i', '', $folded) ?? $skeleton);
        }

        // Finally the pairs that imitate a single letter, e.g. "rn" for "m".
        return strtr($skeleton, self::CONFUSABLE_PAIRS);
    }
}
