<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\AdoptionService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for AdoptionService — the once-per-day adoption rules.
 *
 * @package Felkyo\Tests\Integration
 */
final class AdoptionServiceTest extends DatabaseTestCase
{
    private AdoptionService $adoption;
    private CreatureRepository $creatures;
    private SpeciesRepository $species;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->species = new SpeciesRepository($this->connection);
        $users = new UserRepository($this->connection);

        $this->adoption = new AdoptionService(
            $this->species,
            $this->creatures,
            $users,
            ['cooldown_seconds' => 86400, 'names' => ['Pip', 'Biscuit', 'Clover']]
        );

        $this->userId = $users->create('adopter', 'adopter@example.com', 'hash')->id;
    }

    public function testAdoptingCreatesACreatureOfAnAdoptableSpecies(): void
    {
        $result = $this->adoption->adopt($this->userId);

        $this->assertTrue($result->isSuccessful());

        // The user now owns exactly one creature...
        $owned = $this->creatures->findByOwner($this->userId);
        $this->assertCount(1, $owned);

        // ...and it is one of the adoptable species.
        $adoptableIds = array_map(
            static fn ($species) => $species->id,
            $this->species->findAdoptable()
        );
        $this->assertContains($owned[0]->speciesId, $adoptableIds);
    }

    public function testCanAdoptIsTrueBeforeAndFalseAfterAdopting(): void
    {
        $this->assertTrue($this->adoption->canAdopt($this->userId));

        $this->adoption->adopt($this->userId);

        $this->assertFalse($this->adoption->canAdopt($this->userId));
    }

    public function testASecondAdoptionWithinTheCooldownIsRefused(): void
    {
        $this->adoption->adopt($this->userId);

        $second = $this->adoption->adopt($this->userId);

        $this->assertFalse($second->isSuccessful());
        // Still only the one creature from the first adoption.
        $this->assertCount(1, $this->creatures->findByOwner($this->userId));
    }

    public function testAdoptingWorksAgainOnceTheCooldownHasPassed(): void
    {
        $this->adoption->adopt($this->userId);

        // Simulate a day passing by ageing the "last adopted" stamp.
        $this->connection->exec(
            'UPDATE users SET last_adopted_at = NOW() - INTERVAL 2 DAY WHERE id = ' . $this->userId
        );

        $again = $this->adoption->adopt($this->userId);

        $this->assertTrue($again->isSuccessful());
        $this->assertCount(2, $this->creatures->findByOwner($this->userId));
    }
}
