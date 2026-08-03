<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for PettingRepository — the petting records that back the cooldown and
 * the "times petted" count.
 *
 * @package Felkyo\Tests\Integration
 */
final class PettingRepositoryTest extends DatabaseTestCase
{
    private PettingRepository $pettings;
    private int $creatureId;
    private int $actorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->pettings = new PettingRepository($this->connection);
        $users = new UserRepository($this->connection);
        $creatures = new CreatureRepository($this->connection);
        $species = new SpeciesRepository($this->connection);

        $this->actorId = $users->create('actor', 'actor@example.com', 'hash')->id;
        $ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->creatureId = $creatures->create($ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    public function testRecordAddsToTheTimesPettedCount(): void
    {
        $this->assertSame(0, $this->pettings->countForCreature($this->creatureId));

        $this->pettings->record($this->creatureId, $this->actorId);
        $this->pettings->record($this->creatureId, $this->actorId);

        $this->assertSame(2, $this->pettings->countForCreature($this->creatureId));
    }

    public function testWasPettedRecentlyIsTrueRightAfterAPetByTheSamePerson(): void
    {
        $this->pettings->record($this->creatureId, $this->actorId);

        $this->assertTrue($this->pettings->wasPettedRecentlyBy($this->actorId, $this->creatureId, 60));
    }

    public function testWasPettedRecentlyIsFalseForADifferentPerson(): void
    {
        $this->pettings->record($this->creatureId, $this->actorId);

        // The cooldown is per person: someone else has not petted it recently.
        $this->assertFalse($this->pettings->wasPettedRecentlyBy($this->actorId + 999, $this->creatureId, 60));
    }

    public function testAnOldPetIsOutsideTheCooldownWindow(): void
    {
        // A pet stamped two hours ago should not count for a 60-second window.
        $this->connection->prepare(
            'INSERT INTO pettings (creature_id, actor_user_id, created_at)
             VALUES (?, ?, NOW() - INTERVAL 2 HOUR)'
        )->execute([$this->creatureId, $this->actorId]);

        $this->assertFalse($this->pettings->wasPettedRecentlyBy($this->actorId, $this->creatureId, 60));
    }
}
