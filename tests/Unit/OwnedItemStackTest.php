<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Economy\Item;
use Felkyo\Economy\ItemCategory;
use Felkyo\Economy\OwnedItemStack;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OwnedItemStack — one pile of identical items a player owns.
 *
 * @package Felkyo\Tests\Unit
 *
 * These are unit tests: no database, just the small decisions the value objects
 * make. What is being pinned down here is the meaning of "sellable", because M1.2
 * builds the sell button on it and a wrong answer there either hides a button
 * players should have or shows one that takes their things for nothing.
 */
final class OwnedItemStackTest extends TestCase
{
    /**
     * Build an item with the two numbers that matter and sensible filler for the
     * rest, so each test reads as "price 20, sells for 10" and nothing else.
     */
    private function item(int $price, int $sellValue): Item
    {
        $dishes = new ItemCategory(2, 'dish', 'Dish', '--category-dish', 'bowl', 2);

        return new Item(1, 'honey-treat', 'Honey Treat', 'A sweet golden treat.', $price, $sellValue, $dishes);
    }

    public function testAnItemWithASellValueCanBeSold(): void
    {
        $stack = new OwnedItemStack($this->item(20, 10), 1);

        $this->assertTrue($stack->isSellable());
    }

    public function testAnItemWorthNothingCannotBeSold(): void
    {
        // A sell value of 0 means "no shop wants this", not "this sells for 0".
        // The site can then explain itself instead of offering an empty bargain.
        $stack = new OwnedItemStack($this->item(20, 0), 3);

        $this->assertFalse($stack->isSellable());
        $this->assertFalse($stack->item->isSellable());
    }

    public function testAnEmptyPileCannotBeSoldEvenIfTheItemIsValuable(): void
    {
        // This is the stale-page case: someone left the inventory open in a tab,
        // sold their last treat somewhere else, and came back to the old screen.
        $stack = new OwnedItemStack($this->item(20, 10), 0);

        $this->assertFalse($stack->isSellable());
    }

    public function testAStackRemembersWhatItHoldsAndHowMany(): void
    {
        $stack = new OwnedItemStack($this->item(20, 10), 4);

        $this->assertSame('Honey Treat', $stack->item->name);
        $this->assertSame(4, $stack->quantity);
    }
}
