<?php

declare(strict_types=1);

namespace Felkyo\Guestbook;

/**
 * The outcome of trying to sign a guestbook.
 *
 * @package Felkyo\Guestbook
 *
 * Signing can end in four ordinary ways, none of which is an error worth throwing
 * an exception over: the entry was newly signed, an existing entry was changed,
 * nothing changed because the same message was chosen again, or it was refused
 * (an unknown message, or the once-a-day limit). Returning a small result object
 * keeps the controller down to "show the message, then redirect".
 *
 * Build one with the named shortcuts, never with "new" — signed() reads far more
 * clearly at the call site than new GuestbookResult(true, ...).
 */
final class GuestbookResult
{
    private function __construct(
        private bool $accepted,
        private string $message,
    ) {
    }

    /** A brand-new signature was added. */
    public static function signed(string $message): self
    {
        return new self(true, $message);
    }

    /** An existing signature was swapped for a different message. */
    public static function changed(string $message): self
    {
        return new self(true, $message);
    }

    /**
     * The same message was chosen again, so nothing was written. This counts as
     * accepted — nothing went wrong — and deliberately does NOT use up the
     * once-a-day change, because the visitor did not actually change anything.
     */
    public static function unchanged(string $message): self
    {
        return new self(true, $message);
    }

    /** Refused: an unknown message, or the once-a-day limit has not passed. */
    public static function rejected(string $message): self
    {
        return new self(false, $message);
    }

    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    public function message(): string
    {
        return $this->message;
    }
}
