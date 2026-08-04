<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

/**
 * Picks one item from a weighted list.
 *
 * @package Felkyo\Exploration
 *
 * WHAT THIS IS: the maths behind "a weighted-random reward". Each item has a
 * "weight" — a bigger weight means a bigger share of the chances. For example
 * with weights 85 and 15, the first is picked about 85 times out of 100.
 *
 * WHY THE RANDOMNESS IS SEPARATE: the actual dice roll (a random number) is passed
 * IN to pickByRoll(). That keeps the picking logic pure and easy to test — a test
 * can hand it an exact roll and check exactly which item comes out. The caller
 * (ExplorationService) generates the real random roll.
 */
final class WeightedPicker
{
    /**
     * Add up all the weights. The caller uses this to know the range to roll in.
     *
     * @param array<int, array{weight: int}> $items
     */
    public function totalWeight(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item['weight'];
        }

        return $total;
    }

    /**
     * Choose the item that the given roll lands on. The roll must be in the range
     * 0 .. (totalWeight - 1). We walk the items, adding up their weights, and
     * return the first one the running total passes the roll — which gives each
     * item a share of the outcomes equal to its weight.
     *
     * @param array<int, array{weight: int}> $items A non-empty list of weighted items.
     * @return array{weight: int} The chosen item (same shape as the input items).
     */
    public function pickByRoll(array $items, int $roll): array
    {
        $runningTotal = 0;
        foreach ($items as $item) {
            $runningTotal += $item['weight'];
            if ($roll < $runningTotal) {
                return $item;
            }
        }

        // Fallback: if the roll was out of range, return the last item. With a
        // correct roll (0 .. total-1) this line is never reached.
        return $items[array_key_last($items)];
    }
}
