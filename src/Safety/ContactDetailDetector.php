<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * Finds web addresses, handles and contact details in text a player has written.
 *
 * @package Felkyo\Safety
 *
 * THIS IS THE MOST IMPORTANT FILTER ON THE SITE, and it is worth understanding
 * exactly why, because it is not obvious from the code.
 *
 * Felkyo is built so that a stranger cannot do much harm: there is no messaging,
 * no private channel, and every word one player sends another was written by us.
 * A person who wants to reach a child here has almost nothing to work with —
 * except one thing. If they can get somebody to leave, none of it applies any
 * more. A link, a username on another site, an email address: each of those is a
 * door out of every protection this project has, into somewhere with none of them.
 *
 * So this filter is not about tidiness or spam. It is the lock on that door.
 *
 * WHAT IT HONESTLY DOES AND DOES NOT DO. It stops the casual case completely —
 * somebody pasting their profile link without thinking. It does not stop a
 * determined adult, and nothing at this layer could. What it also does, and this
 * is the part worth valuing, is make the determined case look deliberate: a
 * person who writes "find me on d1sc0rd" has visibly worked at it, and that turns
 * a borderline judgement call into an easy one for whoever reads the report. The
 * filter's job is to make moderation possible, not to be a wall.
 *
 * WHEN IN DOUBT IT REFUSES. A false positive costs somebody a rewritten sentence.
 * A false negative can cost a child. Those are not comparable, so the tuning is
 * deliberately cautious, and the refusal message says what to change.
 */
final class ContactDetailDetector
{
    /**
     * Endings that make something look like a web address. Deliberately a short
     * list of the ones actually seen, rather than every ending that exists —
     * matching "anything.anything" would trip on ordinary sentences with a
     * missing space after a full stop, which honest people type constantly.
     */
    private const DOMAIN_ENDINGS = [
        'com', 'net', 'org', 'io', 'co', 'uk', 'de', 'me', 'gg', 'tv',
        'xyz', 'app', 'link', 'ly', 'be', 'fr', 'nl', 'eu', 'info', 'site',
    ];

    /**
     * Places people go to talk privately. Named individually because the whole
     * risk is somebody moving a conversation off this site, and naming the
     * destination is the clearest signal that is what is happening.
     */
    private const PLATFORM_NAMES = [
        'discord', 'snapchat', 'snap', 'instagram', 'insta', 'tiktok', 'whatsapp',
        'telegram', 'kik', 'skype', 'facebook', 'messenger', 'roblox', 'twitter',
        'reddit', 'youtube', 'twitch', 'steam', 'signal', 'wechat', 'viber',
    ];

    /**
     * Does this text contain something that would let a conversation continue
     * somewhere else?
     */
    public function containsContactDetails(string $text): bool
    {
        return $this->reasonFor($text) !== null;
    }

    /**
     * Why the text was refused, in words a player can act on — or null if there
     * is nothing wrong with it.
     *
     * Returning the reason rather than just "no" matters: golden rule 3 says a
     * refusal names what to do instead. "That looks like a web address" tells
     * somebody what to change; "invalid input" tells them nothing and they will
     * try again with the same thing.
     */
    public function reasonFor(string $text): ?string
    {
        $flat = TextNormaliser::normalise($text);
        $closed = TextNormaliser::withoutSpacing($text);

        if ($this->looksLikeAnEmailAddress($text)) {
            return 'Please don’t include an email address.';
        }

        if ($this->looksLikeAWebAddress($flat) || $this->looksLikeAWebAddress($closed)) {
            return 'Please don’t include web addresses or links.';
        }

        if ($this->namesAnotherPlatform($flat, $closed)) {
            return 'Please don’t point people to other websites or apps.';
        }

        if ($this->looksLikeAHandle($text)) {
            return 'Please don’t include usernames for other sites.';
        }

        return null;
    }

    /**
     * something@something.something — checked before web addresses, so the
     * message can be the more specific one.
     */
    private function looksLikeAnEmailAddress(string $original): bool
    {
        // Checked against what was TYPED, not the normalised form. The normaliser
        // deliberately turns "@" into the letter it imitates, which is right for
        // catching "sc@m" and useless here — it also turned every domain
        // containing an "a" into something that looked like an email address, so
        // "see example.com" was reported as one while this was being written.
        $cleaned = TextNormaliser::withoutHidingCharacters($original);

        return preg_match('/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.[\p{L}]{2,}/u', $cleaned) === 1;
    }

    /**
     * A web address, with or without the http:// part.
     */
    private function looksLikeAWebAddress(string $text): bool
    {
        if (str_contains($text, 'http') || str_contains($text, 'www.')) {
            return true;
        }

        // "somewhere.com" — a word, a dot, and one of the endings we know, with
        // the ending finishing the word so "hello.commander" does not trip it.
        return preg_match('/\b[\p{L}\p{N}-]{2,}\.(' . $this->endings() . ')\b/u', $text) === 1;
    }

    /**
     * The name of somewhere people go to talk privately.
     *
     * TWO PASSES, AND THE SECOND ONE IS DELIBERATELY LIMITED.
     *
     * The first pass looks for the name as a whole word in ordinary text, which
     * catches "add me on snap".
     *
     * The second looks at the text with every gap closed up, which is what catches
     * "d i s c o r d". But closing gaps also joins innocent neighbouring words —
     * "loves naps" becomes "lovesnaps", which contains "snap" — so that pass is
     * used ONLY for names of seven letters or more, where an accidental collision
     * is vanishingly unlikely. Short names like "snap" and "kik" rely on the
     * word-boundary pass alone.
     *
     * That limit was not a guess: "A gentle creature who loves naps." was flagged
     * as advertising Snapchat while this was being written.
     */
    private function namesAnotherPlatform(string $flat, string $closed): bool
    {
        foreach (self::PLATFORM_NAMES as $platform) {
            // As a whole word, in normally spaced text.
            if (preg_match('/\b' . preg_quote($platform, '/') . '\b/u', $flat) === 1) {
                return true;
            }

            // As a run of letters with the spacing picked out of it. Long names only.
            if (mb_strlen($platform) >= 7 && str_contains($closed, $platform)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An "@handle" style username, e.g. "@mira_draws".
     *
     * This one is checked against the ORIGINAL text, not the normalised form,
     * because normalising deliberately turns "@" into the letter it imitates —
     * which is right for catching "sc@m" and wrong for spotting a handle. The two
     * needs genuinely conflict, so this check simply looks at what was typed.
     *
     * A bare "@" followed by a word has no innocent use in a creature's name or a
     * short about text, so it is refused rather than puzzled over.
     */
    private function looksLikeAHandle(string $original): bool
    {
        $cleaned = TextNormaliser::withoutHidingCharacters($original);

        return preg_match('/@\s*[\p{L}\p{N}._-]{2,}/u', $cleaned) === 1;
    }

    /**
     * The domain endings, ready to drop into a pattern.
     */
    private function endings(): string
    {
        return implode('|', self::DOMAIN_ENDINGS);
    }
}
