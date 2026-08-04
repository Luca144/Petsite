<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * Checks user-written text against a list of blocked words.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: a small, simple moderation filter. It is used on text a player
 * writes (a creature's bio, and later a guestbook entry) to keep out words the
 * Product Owner has chosen to disallow. The list comes from config.
 *
 * It matches WHOLE WORDS, case-insensitively — so a blocked word like "scam" is
 * caught on its own but does NOT trip on an innocent word that merely contains it
 * (like "scamper"). This is a deliberately simple approach; it is not a substitute
 * for real moderation, but it is a sensible, readable first line.
 */
final class ContentFilter
{
    /**
     * @param string[] $blockedWords The words that are not allowed.
     */
    public function __construct(private array $blockedWords)
    {
    }

    /**
     * Does the given text contain any blocked word (as a whole word)?
     */
    public function containsBlockedWord(string $text): bool
    {
        foreach ($this->blockedWords as $word) {
            if ($word === '') {
                continue;
            }

            // \b marks a word boundary, so we match the word on its own. preg_quote
            // makes the word safe to drop into the pattern even if it has symbols.
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
