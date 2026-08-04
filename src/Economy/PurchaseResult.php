<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * The outcome of trying to buy an item: success (with the item bought) or a
 * refusal (with a reason, e.g. not enough currency).
 *
 * @package Felkyo\Economy
 *
 * Build one with success() or failed(), never with "new". Each carries a
 * ready-to-show message.
 */
final class PurchaseResult
{
    private function __construct(
        private bool $successful,
        private string $message,
        private ?Item $item,
    ) {
    }

    public static function success(Item $item, string $message): self
    {
        return new self(true, $message, $item);
    }

    public static function failed(string $message): self
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
     * The item that was bought, or null on failure.
     */
    public function item(): ?Item
    {
        return $this->item;
    }
}
