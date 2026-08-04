<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

use Felkyo\Creatures\Creature;

/**
 * The outcome of one exploration click: either a reward (which may include a new
 * creature), or "you've used up your clicks here for now".
 *
 * @package Felkyo\Exploration
 *
 * Build one with reward() or limitReached(), never with "new". Each carries a
 * ready-to-show message.
 */
final class ExplorationResult
{
    private function __construct(
        private bool $limitReached,
        private string $message,
        private ?Creature $creature,
    ) {
    }

    /**
     * A reward was found. $creature is the new creature if one was granted, or
     * null for a "nothing this time" reward.
     */
    public static function reward(string $message, ?Creature $creature): self
    {
        return new self(false, $message, $creature);
    }

    /** The visit's click allowance is used up. */
    public static function limitReached(string $message): self
    {
        return new self(true, $message, null);
    }

    public function isLimitReached(): bool
    {
        return $this->limitReached;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * The creature found on this click, or null if none was granted.
     */
    public function creature(): ?Creature
    {
        return $this->creature;
    }
}
