<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * Rewrites text into a plain form that filters can be matched against.
 *
 * @package Felkyo\Safety
 *
 * WHAT THIS IS FOR: somebody who wants to get a banned word or a web address past
 * a filter does not type it plainly. They type "d i s c o r d", or "sc4m", or
 * "example dot com", or they slip an invisible character into the middle of it.
 * All of those read identically to a human and completely differently to a naive
 * comparison.
 *
 * So before anything is matched, the text is rewritten into one dull, predictable
 * form. Matching then happens against THAT, never against what was typed. The
 * original is what gets stored and shown — this is only ever used for deciding.
 *
 * BE HONEST ABOUT WHAT THIS ACHIEVES. It is not a solution, it is a raised floor.
 * A determined person will get something past it; that is true of every filter
 * ever written, including the ones with a company behind them. What it does do is
 * stop the casual case entirely, and make the deliberate case look deliberate —
 * and that second part matters more than it sounds, because "they wrote sc4m"
 * is something a moderator can act on without hesitating, while an accident is
 * not. The filter's job is to make reports easy to judge, not to be a wall.
 */
final class TextNormaliser
{
    /**
     * Characters that exist mainly to hide or scramble text: zero-width spaces
     * and joiners, and the bidirectional overrides that can make a line render
     * back to front. There is no honest use for these in a creature's name.
     *
     * They are REMOVED here for matching, and refused outright by TextGuard — a
     * name containing them is rejected rather than cleaned, because somebody who
     * has typed a right-to-left override into a pet name is not doing so by
     * accident.
     */
    public const HIDING_CHARACTERS = [
        "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}", // zero-width space/joiners
        "\u{202A}", "\u{202B}", "\u{202C}", "\u{202D}", "\u{202E}", // bidi overrides
        "\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}", // bidi isolates
        "\u{00AD}", // soft hyphen
    ];

    /**
     * Characters people substitute for letters, either to dodge a filter or just
     * to look stylish. Mapped back so "sc4m" and "scam" are the same to a filter.
     */
    private const LOOKALIKES = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '9' => 'g', '@' => 'a', '$' => 's',
        '!' => 'i', '|' => 'i', '£' => 'l', '€' => 'e', '+' => 't',
    ];

    /**
     * Ways of writing a full stop without typing one — the standard trick for
     * getting a web address past a filter that looks for dots.
     */
    private const SPELLED_OUT_DOTS = ['(dot)', '[dot]', '{dot}', ' dot ', ' d0t ', ' punkt ', '(.)'];

    /**
     * Strip the invisible characters out of a piece of text.
     *
     * Kept separate from the full normalise() because TextGuard needs to ask
     * "were any of these present?" before deciding to refuse, which it cannot do
     * once everything has been flattened.
     */
    public static function withoutHidingCharacters(string $text): string
    {
        return str_replace(self::HIDING_CHARACTERS, '', $text);
    }

    /**
     * Does this text contain a character whose purpose is to hide or reverse it?
     */
    public static function containsHidingCharacters(string $text): bool
    {
        return $text !== self::withoutHidingCharacters($text);
    }

    /**
     * The dull, predictable form used for every filter comparison.
     *
     * The order of the steps matters and is worth reading:
     *  1. invisible characters go first, or they would break every later step
     *  2. lowercase, so case games do nothing
     *  3. spelled-out dots become real dots, before spacing is touched
     *  4. lookalike characters become the letters they imitate
     *  5. runs of separators collapse, so "d i s c o r d" closes up
     */
    public static function normalise(string $text): string
    {
        $text = self::withoutHidingCharacters($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(self::SPELLED_OUT_DOTS, '.', $text);
        $text = strtr($text, self::LOOKALIKES);

        // Anything that is not a letter, a digit or a dot becomes a single space.
        // This is what turns "d-i-s-c-o-r-d" and "d_i_s_c_o_r_d" into the same
        // thing, without needing a rule for each separator somebody might invent.
        $text = preg_replace('/[^\p{L}\p{N}.]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * The normalised text with DELIBERATE letter-spacing closed up, and nothing
     * else touched.
     *
     * "s c a m artists" becomes "scam artists". "She loves naps" is left exactly
     * as it is.
     *
     * WHY THIS EXISTS ALONGSIDE withoutSpacing(): closing every gap is too blunt
     * for short words. "loves naps" run together contains "snap", so a blocklist
     * matched against it would refuse an entirely innocent sentence — which
     * genuinely happened while this was being written. But spacing a word out
     * letter by letter is the commonest way to dodge a filter, and refusing to
     * catch it would leave the front door open.
     *
     * So this closes up only runs of SINGLE letters separated by single gaps,
     * which is what letter-spacing looks like and what ordinary writing never
     * does. Real words, being longer than one letter, are untouched.
     */
    public static function withoutLetterSpacing(string $text): string
    {
        $normalised = self::normalise($text);

        // Find runs of at least three single letters each followed by a space
        // (e.g. "s c a m"), and remove the spaces inside just those runs.
        return preg_replace_callback(
            // The lookahead makes the run stop at a word boundary. Without it,
            // "s c a m artists" greedily swallowed the first letter of the next
            // word and produced "scamartists", in which "scam" is no longer a
            // whole word — so the blocklist missed it.
            '/(?:\p{L}\s){2,}\p{L}(?!\p{L})/u',
            static fn (array $match): string => str_replace(' ', '', $match[0]),
            $normalised
        ) ?? $normalised;
    }

    /**
     * The normalised text with ALL spacing removed.
     *
     * This is the form that catches letter-by-letter spacing: "s c a m" becomes
     * "scam". It is deliberately a separate method rather than the default,
     * because closing up every gap also joins innocent neighbouring words — "a
     * dog nam ed pip" would contain "named" — so it is used for looking up known
     * bad words, never for deciding what a sentence means.
     */
    public static function withoutSpacing(string $text): string
    {
        return str_replace(' ', '', self::normalise($text));
    }
}
