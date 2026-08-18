<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The outcome of trying to buy a creature.
 *
 * @package Felkyo\Creatures
 *
 * Built with bought() or refused(), never with "new". The creature is carried so
 * the page can link straight to the new arrival — meeting it is the whole point,
 * and making somebody hunt for it in a list afterwards would waste the moment.
 */
final class CreaturePurchaseResult
{
    private function __construct(
        private bool $successful,
        private string $message,
        private ?Creature $creature,
    ) {
    }

    public static function bought(Creature $creature, string $message): self
    {
        return new self(true, $message, $creature);
    }

    public static function refused(string $message): self
    {
        return new self(false, $message, null);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * The creature that was bought, or null if none was.
     */
    public function creature(): ?Creature
    {
        return $this->creature;
    }
}
