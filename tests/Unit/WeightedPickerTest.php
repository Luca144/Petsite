<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Exploration\WeightedPicker;
use PHPUnit\Framework\TestCase;

/**
 * Tests for WeightedPicker — the weighted-random selection maths.
 *
 * @package Felkyo\Tests\Unit
 *
 * Because the roll is passed in, we can check the exact boundaries of which roll
 * lands on which item — no real randomness needed.
 */
final class WeightedPickerTest extends TestCase
{
    private WeightedPicker $picker;

    /** A loot table like the real one: mostly "nothing", sometimes a creature. */
    private const LOOT = [
        ['type' => 'nothing', 'weight' => 85, 'message' => 'nothing'],
        ['type' => 'creature', 'weight' => 15, 'message' => 'a creature'],
    ];

    protected function setUp(): void
    {
        $this->picker = new WeightedPicker();
    }

    public function testTotalWeightAddsUpTheWeights(): void
    {
        $this->assertSame(100, $this->picker->totalWeight(self::LOOT));
    }

    public function testLowRollsLandOnTheFirstItem(): void
    {
        // Rolls 0..84 fall within the first item's weight of 85.
        $this->assertSame('nothing', $this->picker->pickByRoll(self::LOOT, 0)['type']);
        $this->assertSame('nothing', $this->picker->pickByRoll(self::LOOT, 84)['type']);
    }

    public function testTheBoundaryRollLandsOnTheSecondItem(): void
    {
        // Roll 85 is the first roll that belongs to the second item.
        $this->assertSame('creature', $this->picker->pickByRoll(self::LOOT, 85)['type']);
    }

    public function testTheHighestRollLandsOnTheLastItem(): void
    {
        // Roll 99 is the last valid roll (total - 1).
        $this->assertSame('creature', $this->picker->pickByRoll(self::LOOT, 99)['type']);
    }
}
