<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Creatures\GrowthCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GrowthCalculator — turning XP into a level and a life stage.
 *
 * @package Felkyo\Tests\Unit
 *
 * The boundary cases (exactly at a level/stage threshold) matter most, so they
 * are tested explicitly.
 */
final class GrowthCalculatorTest extends TestCase
{
    private GrowthCalculator $growth;

    protected function setUp(): void
    {
        // 20 XP per level; juvenile starts at level 3, adult at level 6.
        $this->growth = new GrowthCalculator(20, [
            'baby' => 1,
            'juvenile' => 3,
            'adult' => 6,
        ]);
    }

    public function testANewCreatureIsLevelOne(): void
    {
        $this->assertSame(1, $this->growth->levelFor(0));
    }

    public function testXpEarnsLevels(): void
    {
        // 20 XP per level: 20 -> level 2, 40 -> level 3, 100 -> level 6.
        $this->assertSame(2, $this->growth->levelFor(20));
        $this->assertSame(3, $this->growth->levelFor(40));
        $this->assertSame(6, $this->growth->levelFor(100));
    }

    public function testJustBelowALevelThresholdStaysOnTheLowerLevel(): void
    {
        // Boundary case: 39 XP is still level 2 (level 3 begins at 40).
        $this->assertSame(2, $this->growth->levelFor(39));
    }

    public function testANewCreatureIsABaby(): void
    {
        $this->assertSame('baby', $this->growth->stageFor(0));
    }

    public function testReachingTheJuvenileStartLevelBecomesAJuvenile(): void
    {
        // Juvenile begins at level 3, which is 40 XP. Just below is still a baby.
        $this->assertSame('baby', $this->growth->stageFor(39));
        $this->assertSame('juvenile', $this->growth->stageFor(40));
    }

    public function testReachingTheAdultStartLevelBecomesAnAdult(): void
    {
        // Adult begins at level 6, which is 100 XP.
        $this->assertSame('juvenile', $this->growth->stageFor(99));
        $this->assertSame('adult', $this->growth->stageFor(100));
    }

    public function testPlentyOfXpIsAHighLevelAdult(): void
    {
        $this->assertSame('adult', $this->growth->stageFor(5000));
        $this->assertSame(251, $this->growth->levelFor(5000));
    }
}
