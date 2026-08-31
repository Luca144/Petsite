<?php

declare(strict_types=1);

namespace Felkyo\Admin;

/**
 * The outcome of one content-editor action (items, shops, and whatever
 * M2 adds next).
 *
 * @package Felkyo\Admin
 *
 * The same shape as SpeciesEditResult, made shared when the second and
 * third editors arrived: a success flag, one plain sentence for the person,
 * and — when the action touched a thing — its id, so the controller knows
 * where to send them next. (SpeciesEditResult predates this and stays as it
 * is; a rename tour through the working M2.3 code buys nothing.)
 */
final class ContentEditResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?int $contentId,
    ) {
    }

    public static function ok(string $message, ?int $contentId = null): self
    {
        return new self(true, $message, $contentId);
    }

    public static function refused(string $message): self
    {
        return new self(false, $message, null);
    }
}
