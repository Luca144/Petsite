<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for PettingService — the core interaction and cooldown (B.1) and the XP
 * it grants toward growth (B.2).
 *
 * @package Felkyo\Tests\Integration
 */
final class PettingServiceTest extends DatabaseTestCase
{
    private PettingService $petting;
    private CreatureRepository $creatures;
    private int $creatureId;
    private int $actorId;
    private int $otherActorId;

    // A small, explicit petting policy for these tests.
    private const CONFIG = ['cooldown_seconds' => 30, 'happiness_per_pet' => 1, 'xp_per_pet' => 20];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->petting = new PettingService(
            new PettingRepository($this->connection),
            $this->creatures,
            self::CONFIG
        );

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->actorId = $users->create('actor', 'actor@example.com', 'hash')->id;
        $this->otherActorId = $users->create('other', 'other@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    private function creature(): \Felkyo\Creatures\Creature
    {
        return $this->creatures->findById($this->creatureId);
    }

    public function testPettingRaisesHappinessAndGrantsXp(): void
    {
        $result = $this->petting->pet($this->actorId, $this->creature());

        $this->assertTrue($result->isSuccessful());
        $updated = $this->creature();
        $this->assertSame(1, $updated->happiness);
        $this->assertSame(20, $updated->xp);
    }

    public function testPettingIsRejectedDuringCooldown(): void
    {
        $this->petting->pet($this->actorId, $this->creature());

        // The same person immediately petting again is refused...
        $second = $this->petting->pet($this->actorId, $this->creature());
        $this->assertFalse($second->isSuccessful());

        // ...and the stats did not change a second time.
        $updated = $this->creature();
        $this->assertSame(1, $updated->happiness);
        $this->assertSame(20, $updated->xp);
    }

    public function testDifferentPeopleCanEachPetTheSameCreature(): void
    {
        // The cooldown is per person, so two different people both succeed.
        $this->assertTrue($this->petting->pet($this->actorId, $this->creature())->isSuccessful());
        $this->assertTrue($this->petting->pet($this->otherActorId, $this->creature())->isSuccessful());

        $updated = $this->creature();
        $this->assertSame(2, $updated->happiness);
        $this->assertSame(40, $updated->xp);
    }

    public function testEnoughXpFromPettingChangesTheLifeStage(): void
    {
        // With 20 XP per pet, two pets (from two people) reach 40 XP, which is the
        // juvenile threshold (level 3) in the standard config.
        $this->petting->pet($this->actorId, $this->creature());
        $this->petting->pet($this->otherActorId, $this->creature());

        $growth = new GrowthCalculator(20, ['baby' => 1, 'juvenile' => 3, 'adult' => 6]);
        $this->assertSame('juvenile', $growth->stageFor($this->creature()->xp));
    }

    public function testPettingWorksAgainOnceTheCooldownHasPassed(): void
    {
        $this->petting->pet($this->actorId, $this->creature());

        // Simulate the cooldown having passed by ageing the recorded pet.
        $this->connection->exec(
            'UPDATE pettings SET created_at = NOW() - INTERVAL 1 HOUR WHERE actor_user_id = ' . $this->actorId
        );

        $again = $this->petting->pet($this->actorId, $this->creature());
        $this->assertTrue($again->isSuccessful());
        $this->assertSame(40, $this->creature()->xp);
    }
}
