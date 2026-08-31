<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The outcome of one species-editor action.
 *
 * @package Felkyo\Creatures
 *
 * Same shape as ArtResult and RoleChangeResult: a success flag, one plain
 * sentence for the artist, and — when the action made or touched a species —
 * which one, so the controller knows where to send them next.
 */
final class SpeciesEditResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?int $speciesId,
    ) {
    }

    public static function ok(string $message, ?int $speciesId = null): self
    {
        return new self(true, $message, $speciesId);
    }

    public static function refused(string $message): self
    {
        return new self(false, $message, null);
    }
}
