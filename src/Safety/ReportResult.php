<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * The outcome of trying to report something.
 *
 * @package Felkyo\Safety
 *
 * Build one with received() or refused(), never with "new".
 *
 * "received" rather than "success", on purpose. Nothing has been judged yet — a
 * report is not a verdict, and the word should not suggest one to the person who
 * filed it or to the developer reading this later.
 */
final class ReportResult
{
    private function __construct(
        private bool $received,
        private string $message,
    ) {
    }

    public static function received(string $message): self
    {
        return new self(true, $message);
    }

    public static function refused(string $message): self
    {
        return new self(false, $message);
    }

    public function wasReceived(): bool
    {
        return $this->received;
    }

    public function message(): string
    {
        return $this->message;
    }
}
