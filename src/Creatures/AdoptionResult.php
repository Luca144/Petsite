<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The outcome of trying to adopt a creature: success (with the new creature) or
 * refused because the daily limit has not reset yet.
 *
 * @package Felkyo\Creatures
 *
 * As with the other result objects, this keeps the controller simple: "did it
 * work? if so, here is the new creature; if not, here is the message to show."
 * Build one with success() or onCooldown(), never with "new".
 */
final class AdoptionResult
{
    private function __construct(
        private ?Creature $creature,
        private string $message,
    ) {
    }

    public static function success(Creature $creature, string $message): self
    {
        return new self($creature, $message);
    }

    public static function onCooldown(string $message): self
    {
        return new self(null, $message);
    }

    public function isSuccessful(): bool
    {
        return $this->creature !== null;
    }

    /**
     * The newly adopted creature. Only call after checking isSuccessful().
     */
    public function creature(): Creature
    {
        if ($this->creature === null) {
            throw new \LogicException('There is no creature on a failed adoption result.');
        }

        return $this->creature;
    }

    public function message(): string
    {
        return $this->message;
    }
}
