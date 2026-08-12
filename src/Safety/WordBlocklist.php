<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * A list of words that player-written text may not contain.
 *
 * @package Felkyo\Safety
 *
 * WHAT IT IS: the plainest possible filter — a list of words, held as data the
 * humans can edit, matched against text that has first been flattened by
 * TextNormaliser so the obvious dodges do not work.
 *
 * BE HONEST ABOUT WHAT A BLOCKLIST IS WORTH. It catches the obvious and nothing
 * more. Anyone who wants to say something unpleasant can do so with words that
 * are not on any list, and no list has ever been finished. Two consequences worth
 * holding on to:
 *
 *   1. This is NOT the safety system. Reporting is. The blocklist reduces the
 *      volume of the tediously obvious so that the humans reading reports have
 *      attention left for the things that matter.
 *   2. Do not let it grow into a game of whack-a-mole. Every word added is a word
 *      somebody innocent will eventually be refused for, and the list gets worse
 *      at its job as it gets longer.
 *
 * MATCHING IS WHOLE-WORD by default, so a list containing "scam" does not refuse
 * "scamper". The spacing-removed pass is limited to longer words for the same
 * reason the platform-name check is: closing every gap joins innocent neighbours,
 * and a four-letter word will collide with something eventually.
 */
final class WordBlocklist
{
    /**
     * Below this length, a word is only matched as a whole word — never inside
     * the run-together form. Six was chosen because the shortest false positive
     * found while building this ("snap" inside "loves naps") was four, and a
     * margin is cheap.
     */
    private const MINIMUM_LENGTH_FOR_CLOSED_MATCHING = 6;

    /**
     * @param array<int, string> $blockedWords Held as data (config), editable by the humans.
     */
    public function __construct(private array $blockedWords)
    {
    }

    /**
     * Does this text contain a blocked word?
     */
    public function matches(string $text): bool
    {
        return $this->firstMatchIn($text) !== null;
    }

    /**
     * Which blocked word was found, or null if none was.
     *
     * The word itself is returned for the moderation log — not for showing to the
     * player. Telling somebody exactly which word tripped the filter is a free
     * lesson in how to get round it.
     */
    public function firstMatchIn(string $text): ?string
    {
        $flat = TextNormaliser::normalise($text);
        // Letter-spacing closed up, but nothing else joined — so "s c a m" is
        // caught at any word length while "loves naps" stays two words.
        $unspaced = TextNormaliser::withoutLetterSpacing($text);
        $closed = TextNormaliser::withoutSpacing($text);

        foreach ($this->blockedWords as $word) {
            $word = TextNormaliser::normalise($word);

            if ($word === '') {
                continue;
            }

            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $flat) === 1) {
                return $word;
            }

            // The same whole-word check, against text whose letter-spacing has
            // been closed up. This is what catches "s c a m" without the
            // collateral damage of joining every word on the page.
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/u', $unspaced) === 1) {
                return $word;
            }

            if (mb_strlen($word) >= self::MINIMUM_LENGTH_FOR_CLOSED_MATCHING && str_contains($closed, $word)) {
                return $word;
            }
        }

        return null;
    }
}
