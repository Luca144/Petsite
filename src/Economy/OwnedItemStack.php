<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * One pile of identical items that a player owns — "three honey treats".
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: the answer to "what does it mean to own an item?", in the same
 * way that the Creature class is the answer to "what does it mean to own a
 * creature?". Both are things a player owns; they are shaped differently on
 * purpose, and understanding why is the heart of the owned-thing model
 * (docs/owned-things.md).
 *
 * THE SHORT VERSION: items are interchangeable and creatures are not. One honey
 * treat is exactly as good as any other honey treat, so we store a COUNT — this
 * player has three. A creature is named, has a birthday and a history, so it gets
 * its own row and its own identity. Fish and plants will be like creatures, not
 * like treats, which is why they get their own tables when they arrive.
 *
 * This class replaced a loose ['item' => ..., 'quantity' => ...] array. A named
 * type says what it is; an array only says what shape it is.
 */
final class OwnedItemStack
{
    public function __construct(
        public readonly Item $item,
        public readonly int $quantity,
    ) {
    }

    /**
     * Can the player sell from this pile? Both things have to be true: the item
     * has to be worth something to a shop, and they have to actually have one.
     * The quantity check looks obvious, but it is what stops a stale page — one
     * left open in a tab after the last treat was already sold — from offering a
     * sell button for something that is no longer there.
     */
    public function isSellable(): bool
    {
        return $this->item->isSellable() && $this->quantity > 0;
    }
}
