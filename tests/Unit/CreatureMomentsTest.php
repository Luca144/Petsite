<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Creatures\Creature;
use Felkyo\Creatures\CreatureMoments;
use Felkyo\Creatures\Species;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CreatureMoments — the rare speech bubble roll.
 *
 * @package Felkyo\Tests\Unit
 *
 * Randomness is scripted through the injectable number source, so every test
 * here is deterministic: we play the dice, the service reacts.
 */
final class CreatureMomentsTest extends TestCase
{
    private const LINES = [
        '{name} is dozing in a patch of sun.',
        '{name} kept your seat warm.',
    ];

    /** A summary shaped like CreatureProfileBuilder::summariesFor() builds them. */
    private function summary(int $id, string $name): array
    {
        return [
            'creature' => new Creature($id, 1, 1, $name, 0, 0, null, null, true, null, null, null),
            'species' => new Species(1, 'pebblewing', 'Pebblewing', null, true, true),
            'level' => 1,
            'stage' => 'baby',
        ];
    }

    /** A number source that returns the given values, in order. */
    private function scriptedRolls(int ...$rolls): callable
    {
        $queue = $rolls;

        return static function (int $min, int $max) use (&$queue): int {
            return array_shift($queue);
        };
    }

    public function testAChanceOfZeroNeverProducesAMoment(): void
    {
        $moments = new CreatureMoments(self::LINES, 0);

        $this->assertNull($moments->maybeFor([$this->summary(1, 'Bramble')]));
    }

    public function testAChanceOfOneHundredAlwaysProducesAMoment(): void
    {
        // Rolls: 100 for the chance (still <= 100), then first creature, first line.
        $moments = new CreatureMoments(self::LINES, 100, $this->scriptedRolls(100, 0, 0));

        $this->assertNotNull($moments->maybeFor([$this->summary(1, 'Bramble')]));
    }

    public function testNoCreaturesMeansNoMomentEvenAtFullChance(): void
    {
        $moments = new CreatureMoments(self::LINES, 100);

        $this->assertNull($moments->maybeFor([]));
    }

    public function testARollAboveTheChanceStaysQuiet(): void
    {
        // Chance is 20; the die shows 21 — just missed, no moment.
        $moments = new CreatureMoments(self::LINES, 20, $this->scriptedRolls(21));

        $this->assertNull($moments->maybeFor([$this->summary(1, 'Bramble')]));
    }

    public function testARollWithinTheChanceSpeaks(): void
    {
        // Chance is 20; the die shows exactly 20 — the boundary counts as a hit.
        $moments = new CreatureMoments(self::LINES, 20, $this->scriptedRolls(20, 0, 0));

        $moment = $moments->maybeFor([$this->summary(1, 'Bramble')]);

        $this->assertSame('Bramble is dozing in a patch of sun.', $moment['line']);
    }

    public function testTheNamePlaceholderBecomesTheSpeakingCreaturesName(): void
    {
        // Second creature (index 1), second line (index 1).
        $moments = new CreatureMoments(self::LINES, 100, $this->scriptedRolls(1, 1, 1));

        $moment = $moments->maybeFor([$this->summary(1, 'Bramble'), $this->summary(2, 'Clover')]);

        $this->assertSame('Clover kept your seat warm.', $moment['line']);
        $this->assertSame(2, $moment['summary']['creature']->id);
    }

    public function testBothTheCreatureAndTheLineAreChosenByTheDice(): void
    {
        // Same list, different rolls: a different creature AND a different line
        // come out. This is what stops one mascot saying one thing forever.
        $moments = new CreatureMoments(self::LINES, 100, $this->scriptedRolls(1, 0, 1));

        $moment = $moments->maybeFor([$this->summary(1, 'Bramble'), $this->summary(2, 'Clover')]);

        $this->assertSame('Bramble kept your seat warm.', $moment['line']);
        $this->assertSame(1, $moment['summary']['creature']->id);
    }
}
