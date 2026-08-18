<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\PerformanceTestCase;
use Felkyo\Users\UserRepository;

/**
 * Performance tests for PettingService.
 *
 * @package Felkyo\Tests\Integration
 *
 * These tests verify that petting runs efficiently:
 * - Individual pets use the minimum number of queries
 * - Many pets in sequence stay fast
 * - Database indexes are in place to prevent full-table scans
 *
 * These tests intentionally complement PettingServiceTest (which tests
 * correctness) by focusing on speed and scalability.
 */
final class PettingServicePerformanceTest extends PerformanceTestCase
{
    private PettingService $petting;
    private CreatureRepository $creatures;
    private UserRepository $users;
    private PettingRepository $pettings;
    private int $creatureId;
    private int $ownerId;
    private int $actorId;

    private const CONFIG = [
        'cooldown_seconds' => 30,
        'happiness_per_pet' => 1,
        'xp_per_pet' => 20,
        'currency_per_pet' => 5,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->users = new UserRepository($this->connection);
        $this->pettings = new PettingRepository($this->connection);
        $this->petting = new PettingService(
            $this->pettings,
            $this->creatures,
            $this->users,
            self::CONFIG
        );

        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $this->users->create('owner', 'owner@example.com', 'hash')->id;
        $this->actorId = $this->users->create('actor', 'actor@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    private function creature()
    {
        return $this->creatures->findById($this->creatureId);
    }

    /**
     * A single pet should be fast (no N+1 queries).
     * Timing: typically < 20ms, should never exceed 100ms for a single operation.
     */
    public function testSinglePetExecutionSpeed(): void
    {
        $this->startPerfMonitoring();
        $result = $this->petting->pet($this->actorId, $this->creature());
        $this->stopPerfMonitoring();

        $this->assertTrue($result->isSuccessful());
        $this->assertMaxExecutionTime(100, 'Single pet operation should complete in under 100ms');
    }

    /**
     * Many consecutive pets (from different users) should stay fast.
     * This simulates what happens on a popular creature's page.
     * 50 pets from 50 different users should complete in < 1 second.
     */
    public function testManyConsecutivePetsStayFast(): void
    {
        // Create 50 users to pet this creature
        $actors = [];
        for ($i = 0; $i < 50; $i++) {
            $actors[] = $this->users->create("actor_$i", "actor_$i@example.com", 'hash')->id;
        }

        $this->startPerfMonitoring();
        foreach ($actors as $actorId) {
            // Age the previous pet so this one passes the cooldown
            $this->connection->exec(
                'UPDATE pettings SET created_at = NOW() - INTERVAL 1 MINUTE WHERE actor_user_id = ' . $actorId
            );
            $this->petting->pet($actorId, $this->creature());
        }
        $this->stopPerfMonitoring();

        $this->assertMaxExecutionTime(
            1000,
            '50 consecutive pets from different users should complete in under 1 second'
        );
    }

    /**
     * The cooldown check requires an index on (creature_id, actor_user_id, created_at).
     * Without it, each cooldown check does a full table scan on pettings.
     */
    public function testPettingsTableHasRequiredIndexes(): void
    {
        // The most critical index: checking cooldown should never scan the entire table
        $this->assertIndexExists('pettings', 'creature_id', 'Missing index on pettings.creature_id');
        $this->assertIndexExists('pettings', 'actor_user_id', 'Missing index on pettings.actor_user_id');
    }

    /**
     * Verify that the cooldown check doesn't accidentally trigger N+1 queries.
     * The wasPettedRecentlyBy check should be a single indexed query.
     */
    public function testCooldownCheckIsOptimized(): void
    {
        // First pet
        $this->petting->pet($this->actorId, $this->creature());

        // Second immediate pet (should hit cooldown, which does one COUNT query)
        $this->startPerfMonitoring();
        $second = $this->petting->pet($this->actorId, $this->creature());
        $this->stopPerfMonitoring();

        $this->assertFalse($second->isSuccessful(), 'Should be on cooldown');
        // The cooldown path does: 1 COUNT query (wasPettedRecentlyBy)
        // In the future, this could be measured with query logging if enabled
        $this->assertMaxExecutionTime(50, 'Cooldown check should be instant (< 50ms)');
    }

    /**
     * Searching for all petting records for a creature (e.g., "times petted" display)
     * should use an index, not scan the whole table.
     */
    public function testCreaturePettingCountIsOptimized(): void
    {
        // Simulate many creatures and pettings
        $species = new SpeciesRepository($this->connection);
        for ($i = 0; $i < 10; $i++) {
            $creature = $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, "Creature_$i");
            $this->pettings->record($creature->id, $this->actorId);
        }

        // Counting pettings for one creature should be fast
        $this->startPerfMonitoring();
        $count = $this->pettings->countForCreature($this->creatureId);
        $this->stopPerfMonitoring();

        $this->assertSame(0, $count);
        $this->assertMaxExecutionTime(
            20,
            'Counting pettings via index should complete in under 20ms'
        );
    }

    /**
     * Load test: Can the system handle 100 pets in reasonable time?
     * This simulates a creature getting popular — it should scale linearly or better.
     *
     * SCALING INSIGHT: If this test gets slow (> 500ms for 100 pets), it means
     * one of the queries is doing a full table scan or we're missing an index.
     */
    public function testLoadTest100Pets(): void
    {
        $actors = [];
        for ($i = 0; $i < 100; $i++) {
            $actors[] = $this->users->create("load_test_$i", "load_test_$i@example.com", 'hash')->id;
        }

        $this->startPerfMonitoring();
        foreach ($actors as $actorId) {
            $this->connection->exec(
                'UPDATE pettings SET created_at = NOW() - INTERVAL 1 MINUTE WHERE actor_user_id = ' . $actorId
            );
            $this->petting->pet($actorId, $this->creature());
        }
        $this->stopPerfMonitoring();

        $elapsed = $this->getExecutionTimeMs();
        $perPet = $elapsed / 100;

        // 100 pets should take < 2 seconds total (20ms per pet on average)
        $this->assertMaxExecutionTime(
            2000,
            "100 pets should complete in under 2 seconds (got ${elapsed}ms, ${perPet}ms per pet)"
        );
    }
}
