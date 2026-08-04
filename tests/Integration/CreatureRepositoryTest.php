<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use PDOException;

/**
 * Tests for CreatureRepository — reading and writing creatures.
 *
 * @package Felkyo\Tests\Integration
 */
final class CreatureRepositoryTest extends DatabaseTestCase
{
    private CreatureRepository $creatures;
    private UserRepository $users;
    private int $ownerId;
    private int $speciesId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->users = new UserRepository($this->connection);

        // A creature needs a real owner and a real species (foreign keys).
        $this->ownerId = $this->users->create('owner', 'owner@example.com', 'hash')->id;
        $this->speciesId = (new SpeciesRepository($this->connection))->findStarters()[0]->id;
    }

    public function testCreateSavesAndReturnsACreatureWithSensibleDefaults(): void
    {
        $creature = $this->creatures->create($this->ownerId, $this->speciesId, 'Biscuit');

        $this->assertGreaterThan(0, $creature->id);
        $this->assertSame($this->ownerId, $creature->ownerId);
        $this->assertSame($this->speciesId, $creature->speciesId);
        $this->assertSame('Biscuit', $creature->name);
        $this->assertSame(0, $creature->xp);
        $this->assertSame(0, $creature->happiness);
        $this->assertTrue($creature->isPublic);
    }

    public function testFindByIdReturnsTheCreature(): void
    {
        $created = $this->creatures->create($this->ownerId, $this->speciesId, 'Biscuit');

        $found = $this->creatures->findById($created->id);

        $this->assertNotNull($found);
        $this->assertSame('Biscuit', $found->name);
    }

    public function testFindByIdReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->creatures->findById(999999));
    }

    public function testFindByOwnerReturnsOnlyThatOwnersCreatures(): void
    {
        $this->creatures->create($this->ownerId, $this->speciesId, 'Biscuit');
        $this->creatures->create($this->ownerId, $this->speciesId, 'Clover');

        // A second owner with their own creature, which must not be returned.
        $otherOwnerId = $this->users->create('other', 'other@example.com', 'hash')->id;
        $this->creatures->create($otherOwnerId, $this->speciesId, 'Pip');

        $owned = $this->creatures->findByOwner($this->ownerId);

        $this->assertCount(2, $owned);
    }

    public function testACreatureCannotBeOwnedByAUserThatDoesNotExist(): void
    {
        // The foreign key guarantees ownership integrity: you cannot create a
        // creature for a non-existent owner.
        $this->expectException(PDOException::class);
        $this->creatures->create(999999, $this->speciesId, 'Ghost');
    }

    public function testFindRecentPublicReturnsOnlyPublicCreaturesNewestFirst(): void
    {
        // Two public creatures (Biscuit first, then Clover) and one private one.
        $this->creatures->create($this->ownerId, $this->speciesId, 'Biscuit');
        $this->creatures->create($this->ownerId, $this->speciesId, 'Clover');
        $this->connection->prepare(
            'INSERT INTO creatures (owner_id, species_id, name, is_public) VALUES (?, ?, ?, 0)'
        )->execute([$this->ownerId, $this->speciesId, 'SecretPrivate']);

        $recent = $this->creatures->findRecentPublic(10);

        // Only the two public ones, and the newest (Clover) comes first.
        $this->assertCount(2, $recent);
        $this->assertSame('Clover', $recent[0]->name);
        $names = array_map(static fn ($c) => $c->name, $recent);
        $this->assertNotContains('SecretPrivate', $names);
    }

    public function testFindRecentPublicRespectsTheLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->creatures->create($this->ownerId, $this->speciesId, 'Creature' . $i);
        }

        $this->assertCount(3, $this->creatures->findRecentPublic(3));
    }
}
