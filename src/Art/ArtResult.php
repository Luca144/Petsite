<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * The outcome of uploading or deleting a picture.
 *
 * @package Felkyo\Art
 *
 * The same shape as RoleChangeResult: a success flag and one plain sentence
 * for the person, plus the picture involved when there is one.
 */
final class ArtResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?ArtFile $file,
    ) {
    }

    public static function ok(string $message, ?ArtFile $file = null): self
    {
        return new self(true, $message, $file);
    }

    public static function refused(string $message): self
    {
        return new self(false, $message, null);
    }
}
