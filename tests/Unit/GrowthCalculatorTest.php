<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Creatures\GrowthCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GrowthCalculator — turning XP into a life stage.
 *
 * @package Felkyo\Tests\Unit
 *
 * The boundary cases (exactly at a threshold) matter most, so they are tested
 * explicitly.
 */
final class GrowthCalculatorTest extends TestCase
{
    private GrowthCalculator $growth;

    protected function setUp(): void
    {
        $this->growth = new GrowthCalculator([
            'baby' => 0,
            'juvenile' => 100,
            'adult' => 300,
        ]);
    }

    public function testANewCreatureIsABaby(): void
    {
        $this->assertSame('baby', $this->growth->stageFor(0));
    }

    public function testJustBelowTheJuvenileThresholdIsStillABaby(): void
    {
        $this->assertSame('baby', $this->growth->stageFor(99));
    }

    public function testExactlyAtTheJuvenileThresholdIsAJuvenile(): void
    {
        // Boundary case: reaching the threshold exactly must count.
        $this->assertSame('juvenile', $this->growth->stageFor(100));
    }

    public function testJustBelowTheAdultThresholdIsStillAJuvenile(): void
    {
        $this->assertSame('juvenile', $this->growth->stageFor(299));
    }

    public function testExactlyAtTheAdultThresholdIsAnAdult(): void
    {
        $this->assertSame('adult', $this->growth->stageFor(300));
    }

    public function testPlentyOfXpIsAnAdult(): void
    {
        $this->assertSame('adult', $this->growth->stageFor(5000));
    }
}
