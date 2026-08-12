<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * Checks every piece of text a player can put in front of another player.
 *
 * @package Felkyo\Safety
 *
 * WHY ONE CLASS FOR ALL OF THEM. There are only three places on this site where a
 * player chooses their own words: an account name, a creature's name, and a short
 * about or bio text. Every other word passing between players was written by us.
 *
 * That makes these three fields carry the entire weight that messaging would have
 * carried — and it makes it essential that they are checked the SAME way. Three
 * separate sets of rules would mean three different sets of gaps, and the gap
 * nobody remembered would be the one that mattered. So there is one guard, one
 * set of rules, and a small difference in strictness between a name and a bio.
 *
 * WHAT IT CHECKS, AND WHY EACH ONE IS HERE:
 *
 *  - LENGTH. Long text is more room to hide something, and more for a human to
 *    read when it is reported.
 *  - EMPTINESS. A name of spaces, or of characters that render as nothing, is a
 *    player who cannot be referred to, reported or blocked. Every account must be
 *    something you can point at.
 *  - HIDING CHARACTERS. Zero-width spaces and right-to-left overrides are refused
 *    outright rather than stripped, because there is no innocent reason to put one
 *    in a creature's name, and stripping would quietly hide that somebody tried.
 *  - CONTACT DETAILS. The most important check on the site — see
 *    ContactDetailDetector for why.
 *  - BLOCKED WORDS. The obvious cases, so the humans reading reports have their
 *    attention left for the rest.
 *  - IMPERSONATION, for names only: claiming to be staff, or wearing a name that
 *    reads as somebody else's.
 *
 * WHAT IT DELIBERATELY DOES NOT DO: judge meaning. It cannot, and pretending
 * otherwise would be worse than not trying. Reporting is the safety system; this
 * is the floor beneath it.
 */
final class TextGuard
{
    public function __construct(
        private WordBlocklist $blocklist,
        private ContactDetailDetector $contactDetails,
        private ImpersonationGuard $impersonation,
    ) {
    }

    /**
     * Check a name — an account name or a creature's name.
     *
     * Names are held to the stricter standard of the two, because a name follows
     * a player everywhere, appears beside everything they do, and cannot be
     * hidden pending review without breaking the pages it appears on.
     *
     * @param array<int, string> $existingNames Names already in use, for the
     *        lookalike check. Pass only what is worth comparing against — the
     *        caller decides, because comparing against every account on the site
     *        is not something to do on every keystroke.
     */
    public function checkName(string $name, int $maxLength, array $existingNames = []): TextGuardResult
    {
        if (TextNormaliser::containsHidingCharacters($name)) {
            return TextGuardResult::refused(
                'Please use ordinary letters and numbers — that name has hidden characters in it.'
            );
        }

        $trimmed = trim($name);

        if ($trimmed === '' || TextNormaliser::withoutSpacing($trimmed) === '') {
            return TextGuardResult::refused('Please choose a name with some letters in it.');
        }

        if ($trimmed !== $name) {
            return TextGuardResult::refused('Please remove the spaces at the start or end of the name.');
        }

        if (mb_strlen($trimmed) > $maxLength) {
            return TextGuardResult::refused("Please keep the name to {$maxLength} characters or fewer.");
        }

        // Runs of spaces inside a name are how somebody makes one name look like
        // several, or pushes a word out of sight in a narrow column.
        if (preg_match('/\s{2,}/u', $trimmed) === 1) {
            return TextGuardResult::refused('Please use single spaces in the name.');
        }

        if ($this->impersonation->claimsAuthority($trimmed)) {
            return TextGuardResult::refused(
                'That name looks like it belongs to the Felkyo team, so it is kept aside. Please choose another.'
            );
        }

        foreach ($existingNames as $existing) {
            if ($this->impersonation->looksLikeTheSameNameAs($trimmed, $existing)) {
                return TextGuardResult::refused('That name is too close to one already in use. Please choose another.');
            }
        }

        return $this->checkSharedRules($trimmed);
    }

    /**
     * Check a longer free text — a creature's bio or a profile's about.
     *
     * Bios are the highest-risk of the three fields: they are long enough to hold
     * a whole approach, and long enough to bury a contact detail in the middle of
     * something friendly. They get the same rules as a name apart from the
     * impersonation checks, which only make sense for something you are called.
     */
    public function checkLongText(string $text, int $maxLength): TextGuardResult
    {
        if (TextNormaliser::containsHidingCharacters($text)) {
            return TextGuardResult::refused(
                'Please use ordinary letters — there are hidden characters in that text.'
            );
        }

        $trimmed = trim($text);

        // An empty bio is perfectly fine, unlike an empty name.
        if ($trimmed === '') {
            return TextGuardResult::accepted('');
        }

        if (mb_strlen($trimmed) > $maxLength) {
            return TextGuardResult::refused("Please keep it to {$maxLength} characters or fewer.");
        }

        return $this->checkSharedRules($trimmed);
    }

    /**
     * The checks that apply to every field, whatever it is.
     */
    private function checkSharedRules(string $text): TextGuardResult
    {
        $contactReason = $this->contactDetails->reasonFor($text);
        if ($contactReason !== null) {
            // The message says what to change. It does NOT say which pattern
            // matched — that would be a lesson in how to word it next time.
            return TextGuardResult::refused($contactReason . ' Everything on Felkyo stays on Felkyo.');
        }

        if ($this->blocklist->matches($text)) {
            return TextGuardResult::refused('Please keep it friendly — some words in there are not allowed.');
        }

        return TextGuardResult::accepted($text);
    }
}
