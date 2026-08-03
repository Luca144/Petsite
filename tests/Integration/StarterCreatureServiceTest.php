<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;

/**
 * Tests for StarterCreatureService — giving a new player their first creature.
 *
 * @package Felkyo\Tests\Integration
 */
final class StarterCreatureServiceTest extends DatabaseTestCase
{
    private StarterCreatureService $starter;
    private SpeciesRepository $species;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->species = new SpeciesRepository($this->connection);
        $this->starter = new StarterCreatureService(
            $this->species,
            new CreatureRepository($this->connection),
            ['Pip', 'Biscuit', 'Clover']
        );

        $this->user = (new UserRepository($this->connection))
            ->create('newbie', 'newbie@example.com', 'hash');
    }

    public function testItGrantsACreatureOwnedByTheUser(): void
    {
        $creature = $this->starter->grantStarterTo($this->user);

        $this->assertSame($this->user->id, $creature->ownerId);
        $this->assertNotSame('', $creature->name);
    }

    public function testTheStarterCreatureIsAStarterSpecies(): void
    {
        $creature = $this->starter->grantStarterTo($this->user);

        $species = $this->species->findById($creature->speciesId);
        $this->assertNotNull($species);
        $this->assertTrue($species->isStarter);
    }

    public function testTheChoiceIsTheSameForTheSameUser(): void
    {
        // The species and name are chosen from the user's id, so a given user
        // always gets the same starter (which is what makes it testable).
        $first = $this->starter->grantStarterTo($this->user);
        $second = $this->starter->grantStarterTo($this->user);

        $this->assertSame($first->speciesId, $second->speciesId);
        $this->assertSame($first->name, $second->name);
    }
}
