<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\Creature;
use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\UserRepository;

/**
 * Tests for CreatureBioService — validating and saving a bio.
 *
 * @package Felkyo\Tests\Integration
 */
final class CreatureBioServiceTest extends DatabaseTestCase
{
    private CreatureBioService $bioService;
    private CreatureRepository $creatures;
    private int $creatureId;
    private int $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->bioService = new CreatureBioService(
            $this->creatures,
            Guards::textGuard(['spam', 'scam']),
            500
        );

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    private function creature(): Creature
    {
        return $this->creatures->findById($this->creatureId);
    }

    public function testAValidBioIsSaved(): void
    {
        $result = $this->bioService->updateBio($this->creature(), $this->ownerId, 'A gentle creature who loves naps.');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('A gentle creature who loves naps.', $this->creature()->bio);
    }

    public function testABioThatIsTooLongIsRejected(): void
    {
        $tooLong = str_repeat('a', 501);

        $result = $this->bioService->updateBio($this->creature(), $this->ownerId, $tooLong);

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($this->creature()->bio); // unchanged
    }

    public function testABioWithABlockedWordIsRejected(): void
    {
        $result = $this->bioService->updateBio($this->creature(), $this->ownerId, 'buy my spam now');

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($this->creature()->bio);
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        $this->bioService->updateBio($this->creature(), $this->ownerId, '   hello there   ');

        $this->assertSame('hello there', $this->creature()->bio);
    }
}
