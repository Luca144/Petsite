<?php

declare(strict_types=1);

namespace Felkyo\Pound;

/**
 * The answer to a pound action: did it happen, one sentence to show the
 * player, and (when useful) the creature to go to next.
 *
 * @package Felkyo\Pound
 */
final class PoundResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?int $creatureId,
    ) {
    }

    public static function ok(string $message, ?int $creatureId = null): self
    {
        return new self(true, $message, $creatureId);
    }

    public static function refused(string $message): self
    {
        return new self(false, $message, null);
    }
}
