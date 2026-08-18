<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Creatures\MoodCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests the mood rules — and mostly tests that being away costs you nothing.
 *
 * @package Felkyo\Tests\Unit
 *
 * The mechanic M2 borrows from is built on guilt: a creature that suffers while
 * you are not looking, so that you feel you must keep looking. Golden rule 10
 * forbids that outright, and the tests below are how the rule is kept honest
 * rather than merely written down in a comment.
 *
 * The important one is testACreatureLeftAloneForAYearIsStillAboveTheFloor. If
 * somebody later "balances" the decay and that test goes red, the game has
 * quietly become one that punishes people for having a life.
 */
final class MoodCalculatorTest extends TestCase
{
    private const HOUR = 3600;
    private const DAY = 86400;

    private const CONFIG = [
        'starting_happiness' => 80,
        'starting_energy' => 100,
        'happiness_decay_per_day' => 5,
        'happiness_floor' => 20,
        'energy_recovery_per_hour' => 10,
        'resting_below' => 20,
        'favourite_treat_multiplier' => 2,
        'disliked_treat_divisor' => 4,
        'happiness_words' => [
            0 => 'sleepy',
            40 => 'content',
            60 => 'happy',
            85 => 'very happy',
        ],
    ];

    private function calculator(array $overrides = []): MoodCalculator
    {
        return new MoodCalculator($overrides + self::CONFIG);
    }

    // ---- Happiness fades, gently, and then stops ----

    public function testHappinessIsUnchangedTheMomentItIsSet(): void
    {
        $this->assertSame(80, $this->calculator()->happinessNow(80, 0));
    }

    public function testHappinessFallsByTheDailyRate(): void
    {
        $this->assertSame(75, $this->calculator()->happinessNow(80, self::DAY));
        $this->assertSame(70, $this->calculator()->happinessNow(80, 2 * self::DAY));
    }

    public function testPartOfADayCostsPartOfTheDailyRate(): void
    {
        // Half a day at 5 a day is 2.5, rounded down to 2 — rounding DOWN so the
        // creature is never worse off than the arithmetic says.
        $this->assertSame(78, $this->calculator()->happinessNow(80, self::DAY / 2));
    }

    public function testHappinessStopsAtTheFloor(): void
    {
        // 80 would reach zero in sixteen days if nothing stopped it.
        $this->assertSame(20, $this->calculator()->happinessNow(80, 30 * self::DAY));
    }

    public function testACreatureLeftAloneForAYearIsStillAboveTheFloor(): void
    {
        // THE RULE THIS WHOLE SYSTEM IS BUILT AROUND. However long somebody is
        // away — a holiday, an exam term, a bad year — their creature is sleepy
        // and pleased to see them. It is never sad, and nothing has been lost.
        // If this test fails, the game now punishes absence. Do not "fix" it by
        // changing the expectation.
        $afterAYear = $this->calculator()->happinessNow(80, 365 * self::DAY);

        $this->assertSame(20, $afterAYear);
        $this->assertGreaterThanOrEqual(self::CONFIG['happiness_floor'], $afterAYear);
    }

    public function testACreatureSomehowBelowTheFloorIsLiftedBackToIt(): void
    {
        // Only a config change could produce this, but the floor must hold
        // regardless of how a value got below it.
        $this->assertSame(20, $this->calculator()->happinessNow(5, 0));
    }

    // ---- Energy comes back on its own ----

    public function testEnergyRecoversOverTime(): void
    {
        $this->assertSame(50, $this->calculator()->energyNow(40, self::HOUR));
        $this->assertSame(70, $this->calculator()->energyNow(40, 3 * self::HOUR));
    }

    public function testEnergyStopsAtFull(): void
    {
        $this->assertSame(100, $this->calculator()->energyNow(40, 100 * self::HOUR));
    }

    public function testEnergyNeverGoesBelowZero(): void
    {
        $this->assertSame(0, $this->calculator()->afterSpending(3, 10));
    }

    // ---- What one interaction does ----

    public function testKindnessRaisesHappinessButNotPastFull(): void
    {
        $this->assertSame(85, $this->calculator()->afterGaining(80, 5));
        $this->assertSame(100, $this->calculator()->afterGaining(98, 5));
    }

    public function testRestingRaisesEnergyButNotPastFull(): void
    {
        $this->assertSame(70, $this->calculator()->afterResting(50, 20));
        $this->assertSame(100, $this->calculator()->afterResting(95, 20));
    }

    // ---- Tastes ----

    public function testAFavouriteTreatIsWorthDouble(): void
    {
        $this->assertSame(20, $this->calculator()->tasteAdjusted(10, adores: true, dislikes: false));
    }

    public function testADislikedTreatIsWorthAQuarter(): void
    {
        $this->assertSame(3, $this->calculator()->tasteAdjusted(10, adores: false, dislikes: true));
    }

    public function testADislikedTreatNeverDropsToNothing(): void
    {
        // THE FLOOR THAT MATTERS. Integer division would take a small effect to
        // zero, which would read as the creature refusing a gift — and refusing a
        // gift is not something this game does. Anything that helped at all still
        // helps at least a little, so a treat is never wasted and never a rebuff.
        foreach ([1, 2, 3, 4] as $small) {
            $this->assertGreaterThan(
                0,
                $this->calculator()->tasteAdjusted($small, adores: false, dislikes: true),
                'A small disliked treat was worth nothing at all.'
            );
        }
    }

    public function testTasteNeverMakesAnEffectNegative(): void
    {
        // There is deliberately no way to express a treat that harms a creature.
        // If one ever appears in the data, it arrives here as zero, not as damage.
        $this->assertSame(0, $this->calculator()->tasteAdjusted(0, adores: false, dislikes: true));
        $this->assertSame(0, $this->calculator()->tasteAdjusted(-5, adores: false, dislikes: true));
        $this->assertSame(0, $this->calculator()->tasteAdjusted(-5, adores: true, dislikes: false));
    }

    public function testATreatWithNoEffectStaysWithoutEffect(): void
    {
        // Liking something does not conjure an effect out of nothing.
        $this->assertSame(0, $this->calculator()->tasteAdjusted(0, adores: true, dislikes: false));
    }

    public function testNoOpinionLeavesTheEffectAlone(): void
    {
        $this->assertSame(10, $this->calculator()->tasteAdjusted(10, adores: false, dislikes: false));
    }

    // ---- The words ----

    public function testEachBandHasItsOwnWord(): void
    {
        $calculator = $this->calculator();

        $this->assertSame('very happy', $calculator->wordFor(100));
        $this->assertSame('very happy', $calculator->wordFor(85));
        $this->assertSame('happy', $calculator->wordFor(84));
        $this->assertSame('happy', $calculator->wordFor(60));
        $this->assertSame('content', $calculator->wordFor(59));
        $this->assertSame('sleepy', $calculator->wordFor(20));
    }

    public function testNoBandIsCalledSomethingUnkind(): void
    {
        // The words are content and can be reworded freely — but not into the
        // vocabulary this system exists to avoid. A creature is never described
        // as sad, starving, dying, or neglected, because none of those are things
        // this game does to anybody.
        $unkind = ['sad', 'miserable', 'starving', 'hungry', 'dying', 'sick', 'neglected', 'lonely'];

        foreach (self::CONFIG['happiness_words'] as $word) {
            foreach ($unkind as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $forbidden,
                    $word,
                    'A happiness band is called "' . $word . '". Golden rule 11: when in doubt, gentler.'
                );
            }
        }
    }

    public function testAMissingWordListStillProducesAWordAndNeverANumber(): void
    {
        $calculator = $this->calculator(['happiness_words' => []]);

        $this->assertSame('content', $calculator->wordFor(73));
    }

    // ---- The whole mood, assembled ----

    public function testATiredCreatureIsMarkedAsResting(): void
    {
        $mood = $this->calculator()->moodFor(80, 0, 10, 0);

        $this->assertTrue($mood->isResting);
        $this->assertSame(10, $mood->energy);
    }

    public function testARestedCreatureIsNotMarkedAsResting(): void
    {
        $this->assertFalse($this->calculator()->moodFor(80, 0, 90, 0)->isResting);
    }

    public function testTheSentenceReadsLikeSomebodyWroteIt(): void
    {
        $lively = $this->calculator()->moodFor(90, 0, 90, 0);
        $this->assertSame('Biscuit is very happy.', $lively->sentence('Biscuit'));

        // A resting creature is DOING something, not failing at something.
        $dozing = $this->calculator()->moodFor(90, 0, 5, 0);
        $this->assertSame('Biscuit is having a doze, and is very happy.', $dozing->sentence('Biscuit'));
    }

    public function testBothReadingsAgeIndependently(): void
    {
        // A creature petted days ago but rested since: happiness has faded, energy
        // is back. The two clocks are separate on purpose — being tired and being
        // unhappy are different things and recover at different speeds.
        $mood = $this->calculator()->moodFor(80, 4 * self::DAY, 20, 8 * self::HOUR);

        $this->assertSame(60, $mood->happiness);
        $this->assertSame(100, $mood->energy);
    }
}
