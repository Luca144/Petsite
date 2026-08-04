<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Exploration\ExplorationRepository;
use Felkyo\Exploration\ExplorationService;
use Felkyo\Exploration\WeightedPicker;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for ExplorationService — the per-visit click limit and reward granting.
 *
 * @package Felkyo\Tests\Integration
 *
 * To keep the tests certain rather than random, they use single-entry loot tables
 * (always a creature, or always nothing). The weighting maths itself is tested
 * separately in WeightedPickerTest.
 */
final class ExplorationServiceTest extends DatabaseTestCase
{
    private ExplorationService $exploration;
    private CreatureRepository $creatures;
    private int $userId;

    private const AREA_SLUG = 'test-grove';
    private const AREA_CREATURE = ['loot' => [['type' => 'creature', 'weight' => 1, 'message' => 'A creature!']]];
    private const AREA_NOTHING = ['loot' => [['type' => 'nothing', 'weight' => 1, 'message' => 'Nothing here.']]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('exploration_visits', 'pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->exploration = new ExplorationService(
            new ExplorationRepository($this->connection),
            new WeightedPicker(),
            $this->creatures,
            new SpeciesRepository($this->connection),
            ['clicks_per_visit' => 3, 'window_seconds' => 3600, 'creature_names' => ['Pip']]
        );

        $this->userId = (new UserRepository($this->connection))
            ->create('explorer', 'explorer@example.com', 'hash')->id;
    }

    public function testACreatureRewardGrantsANewCreature(): void
    {
        $result = $this->exploration->explore($this->userId, self::AREA_SLUG, self::AREA_CREATURE);

        $this->assertFalse($result->isLimitReached());
        $this->assertNotNull($result->creature());
        $this->assertCount(1, $this->creatures->findByOwner($this->userId));
    }

    public function testANothingRewardGrantsNoCreature(): void
    {
        $result = $this->exploration->explore($this->userId, self::AREA_SLUG, self::AREA_NOTHING);

        $this->assertFalse($result->isLimitReached());
        $this->assertNull($result->creature());
        $this->assertCount(0, $this->creatures->findByOwner($this->userId));
    }

    public function testTheClickLimitIsEnforced(): void
    {
        // Three searches are allowed (clicks_per_visit = 3)...
        for ($i = 0; $i < 3; $i++) {
            $this->assertFalse($this->exploration->explore($this->userId, self::AREA_SLUG, self::AREA_NOTHING)->isLimitReached());
        }

        // ...the fourth is refused.
        $this->assertTrue($this->exploration->explore($this->userId, self::AREA_SLUG, self::AREA_NOTHING)->isLimitReached());
    }

    public function testRemainingClicksCountsDown(): void
    {
        $this->assertSame(3, $this->exploration->remainingClicks($this->userId, self::AREA_SLUG));

        $this->exploration->explore($this->userId, self::AREA_SLUG, self::AREA_NOTHING);

        $this->assertSame(2, $this->exploration->remainingClicks($this->userId, self::AREA_SLUG));
    }

    public function testTheLimitRefreshesAfterTheWindowPasses(): void
    {
        // Use up all three searches.
        for ($i = 0; $i < 3; $i++) {
            $this->exploration->explore($this->userId, self::AREA_SLUG, self::AREA_NOTHING);
        }
        $this->assertSame(0, $this->exploration->remainingClicks($this->userId, self::AREA_SLUG));

        // Simulate the window having started two hours ago (past the 1-hour window).
        $this->connection->exec(
            "UPDATE exploration_visits SET window_started_at = NOW() - INTERVAL 2 HOUR
              WHERE user_id = " . $this->userId . " AND area_slug = '" . self::AREA_SLUG . "'"
        );

        // The allowance is fresh again.
        $this->assertSame(3, $this->exploration->remainingClicks($this->userId, self::AREA_SLUG));
        $this->assertFalse($this->exploration->explore($this->userId, self::AREA_SLUG, self::AREA_NOTHING)->isLimitReached());
    }
}
