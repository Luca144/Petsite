<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Tests\DatabaseTestCase;

/**
 * A creature's progress is worked out, never stored.
 *
 * @package Felkyo\Tests\Integration
 *
 * THE RULE (docs/house-patterns.md section 6): level and life stage are calculated
 * from what a creature has done. They are not columns and must never become
 * columns.
 *
 * WHY IT MATTERS ENOUGH TO TEST. Two stored numbers that are supposed to agree
 * eventually do not. A creature whose stored level says 4 while its experience says
 * 6 is a bug nobody can explain and a support question nobody can answer — and the
 * fix is always a one-off script, because by then the wrong values are real data.
 *
 * Adding a "level" column is also a very reasonable-looking thing to do. It makes
 * one query faster. That is exactly why this test exists: to make the cost visible
 * at the moment somebody reaches for it, rather than a year later.
 */
final class DerivedProgressionTest extends DatabaseTestCase
{
    public function testTheCreaturesTableStoresNoProgressionItCouldCalculate(): void
    {
        $columns = $this->connection->query(
            "SELECT column_name FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'creatures'"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $lowercase = array_map('strtolower', $columns);

        foreach (['level', 'stage', 'life_stage'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $lowercase,
                sprintf(
                    'creatures.%s exists. Level and stage are calculated from xp by '
                    . 'GrowthCalculator — storing them as well creates two answers that '
                    . 'will disagree, and the disagreement becomes real data.',
                    $forbidden
                )
            );
        }

        // The one number that IS stored, because it cannot be derived from anything.
        $this->assertContains('xp', $lowercase, 'creatures.xp is the single source of growth.');
    }

    public function testTheSameExperienceAlwaysGivesTheSameLevelAndStage(): void
    {
        // Calculated means repeatable: no clock, no randomness, no stored state.
        // Ask twice, get the same answer — which is what makes it safe to not store.
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $growth = new GrowthCalculator(
            $config['gameplay']['growth']['xp_per_level'],
            $config['gameplay']['growth']['stage_start_levels']
        );

        foreach ([0, 19, 20, 21, 100, 999] as $xp) {
            $this->assertSame($growth->levelFor($xp), $growth->levelFor($xp));
            $this->assertSame($growth->stageFor($xp), $growth->stageFor($xp));
        }
    }

    public function testGrowthIsDrivenByWhatTheCreatureHasActuallyDone(): void
    {
        // Petting is the interaction; xp is its record. This pins down that the two
        // move together, so "level is derived from interaction" stays true rather
        // than becoming "level is derived from a number somebody can set".
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $growth = new GrowthCalculator(
            $config['gameplay']['growth']['xp_per_level'],
            $config['gameplay']['growth']['stage_start_levels']
        );

        $perPet = $config['gameplay']['petting']['xp_per_pet'];
        $perLevel = $config['gameplay']['growth']['xp_per_level'];

        $this->assertSame(1, $growth->levelFor(0), 'A creature starts at level 1.');
        $this->assertGreaterThan(
            $growth->levelFor(0),
            $growth->levelFor($perPet * (int) ceil($perLevel / max(1, $perPet))),
            'Enough petting must raise the level.'
        );
    }
}
