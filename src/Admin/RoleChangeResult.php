<?php

declare(strict_types=1);

namespace Felkyo\Admin;

/**
 * The outcome of trying to grant or revoke a role.
 *
 * @package Felkyo\Admin
 *
 * The same shape as the other result objects (ReportResult, GuestbookResult):
 * a success flag and one plain-language sentence for the person, so the
 * controller can flash-and-redirect without composing wording of its own.
 */
final class RoleChangeResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
    ) {
    }

    /** The change happened (or was already true — a harmless no-op). */
    public static function ok(string $message): self
    {
        return new self(true, $message);
    }

    /** The change was refused, and the message says why in plain words. */
    public static function refused(string $message): self
    {
        return new self(false, $message);
    }
}
