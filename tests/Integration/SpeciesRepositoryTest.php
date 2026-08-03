<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;

/**
 * Tests for SpeciesRepository — reading the seeded creature species.
 *
 * @package Felkyo\Tests\Integration
 *
 * These rely on the base species seeded by the migration (they are not cleared,
 * because they are reference content the app always needs).
 */
final class SpeciesRepositoryTest extends DatabaseTestCase
{
    private SpeciesRepository $species;

    protected function setUp(): void
    {
        parent::setUp();
        $this->species = new SpeciesRepository($this->connection);
    }

    public function testFindStartersReturnsOnlyStarterSpecies(): void
    {
        $starters = $this->species->findStarters();

        $this->assertNotEmpty($starters);
        foreach ($starters as $species) {
            $this->assertTrue($species->isStarter);
        }
    }

    public function testFindByIdReturnsTheMatchingSpecies(): void
    {
        // Take a real seeded species and look it up by its id.
        $someSpecies = $this->species->findStarters()[0];

        $found = $this->species->findById($someSpecies->id);

        $this->assertNotNull($found);
        $this->assertSame($someSpecies->slug, $found->slug);
    }

    public function testFindByIdReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->species->findById(999999));
    }

    public function testFindAdoptableReturnsOnlyAdoptableSpecies(): void
    {
        $adoptable = $this->species->findAdoptable();

        $this->assertNotEmpty($adoptable);
        foreach ($adoptable as $species) {
            $this->assertTrue($species->isAdoptable);
        }
    }

    public function testAllReturnsEverySeededSpecies(): void
    {
        // The seed migration adds three base species; there should be at least those.
        $this->assertGreaterThanOrEqual(3, count($this->species->all()));
    }
}
