<?php

declare(strict_types=1);

namespace Felkyo\Guestbook;

/**
 * The fixed list of messages a visitor may choose when signing a guestbook.
 *
 * @package Felkyo\Guestbook
 *
 * WHY THE GUESTBOOK WORKS THIS WAY: a normal guestbook lets people type whatever
 * they like, which means spam, abuse, and a moderation job that never ends. Ours
 * does not. A visitor picks one of the messages listed in config, and we store
 * only its KEY. Nothing a visitor types is ever saved, so there is simply nothing
 * to spam with — the safety comes from the design, not from a filter fighting a
 * losing battle.
 *
 * This class wraps that config list so the rest of the code has one obvious place
 * to ask "is this a real message?" and "what does this key say?".
 */
final class GuestbookMessages
{
    /**
     * What we show when an entry points at a message that has since been removed
     * from config. Old entries must never break just because the Product Owner
     * tidied the list, so they fall back to this neutral wording.
     */
    public const RETIRED_MESSAGE_TEXT = 'A kind word.';

    /**
     * @param array<string, string> $messages Message key => the text players read.
     */
    public function __construct(private array $messages)
    {
    }

    /**
     * Every message, ready to build the chooser on the creature page.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Is this one of the messages a visitor is actually allowed to choose?
     *
     * This is the check that stops someone editing the form in their browser and
     * submitting a key we never offered.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->messages);
    }

    /**
     * The text to display for a stored key, falling back for retired messages.
     */
    public function textFor(string $key): string
    {
        return $this->messages[$key] ?? self::RETIRED_MESSAGE_TEXT;
    }
}
