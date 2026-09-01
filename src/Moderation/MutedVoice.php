<?php

declare(strict_types=1);

namespace Felkyo\Moderation;

/**
 * The one question every free-text endpoint asks: "may this player write
 * right now?" (M2.7)
 *
 * @package Felkyo\Moderation
 *
 * Muting pauses NEW WORDS — bios, about texts, names, guestbook signing
 * — and nothing else. The check lives in the controllers that accept
 * text, next to their CSRF and rate-limit checks, because that is where
 * every other per-request guard already lives; this class exists so all
 * four of them refuse with the SAME kind sentence, and so a fifth
 * free-text feature has one obvious thing to add (the developer guide
 * says so too).
 *
 * The answer is read fresh from the database on every call — a mute
 * lifted early works immediately, and one that expired overnight needs
 * nobody to flip a switch.
 */
final class MutedVoice
{
    public function __construct(private ModerationRepository $moderation)
    {
    }

    /**
     * The refusal to show, or null when the player may write. One
     * sentence, the same everywhere: what is paused, that everything
     * else still works, and that it ends (golden rules 3 to 5).
     */
    public function refusalFor(int $userId): ?string
    {
        if (!$this->moderation->isMuted($userId)) {
            return null;
        }

        return 'Writing is paused on your account for a while — a notice at the top '
            . 'of your home page says why. Everything else still works.';
    }
}
