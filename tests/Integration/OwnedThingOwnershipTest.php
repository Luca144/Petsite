<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ShopRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\UserRepository;

/**
 * Proves that one player cannot see or change another player's things.
 *
 * @package Felkyo\Tests\Integration
 *
 * WHY THIS FILE EXISTS (increment M1.1): the owned-thing model's central security
 * rule is that every read and write of something a player owns names the acting
 * player in the query itself, rather than fetching a row and checking the owner in
 * PHP afterwards. Both work when written carefully. Only one of them still works
 * after somebody deletes a line by accident.
 *
 * Every test here is written from the ATTACKER's side: not "the owner can do this"
 * but "a stranger is refused". CLAUDE.md asks for exactly that — a test that the
 * bad case is refused, not only that the good case is allowed. When fish and
 * plants arrive (M10, M12) they get their own tests in this same shape; the recipe
 * in docs/owned-things.md says so.
 *
 * The scenario throughout: Mira owns things, Rowan does not, and Rowan tries.
 */
final class OwnedThingOwnershipTest extends DatabaseTestCase
{
    private CreatureRepository $creatures;
    private InventoryRepository $inventory;
    private int $miraId;
    private int $rowanId;
    private int $mirasCreatureId;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $users = new UserRepository($this->connection);
        $this->creatures = new CreatureRepository($this->connection);
        $this->inventory = new InventoryRepository($this->connection);

        $this->miraId = $users->create('mira', 'mira@example.com', 'hash')->id;
        $this->rowanId = $users->create('rowan', 'rowan@example.com', 'hash')->id;

        $starterSpeciesId = (new SpeciesRepository($this->connection))->findStarters()[0]->id;
        $this->mirasCreatureId = $this->creatures->create($this->miraId, $starterSpeciesId, 'Biscuit')->id;

        $shops = new ShopRepository($this->connection);
        $this->itemId = $shops->findItems($shops->findBySlug('general-store')->id)[0]->id;
    }

    public function testRowanCannotWriteOnMirasCreatureEvenIfEveryCheckAboveIsSkipped(): void
    {
        // This calls the repository DIRECTLY, deliberately going around the
        // controller and the service. That is the whole point: it is a rehearsal
        // of the day somebody refactors those two layers and drops a check. The
        // owner named in the WHERE clause has to hold the line on its own.
        $this->creatures->updateBio($this->mirasCreatureId, $this->rowanId, 'I was here.');

        $this->assertNull(
            $this->creatures->findById($this->mirasCreatureId)->bio,
            "Rowan wrote on Mira's creature by calling the repository directly."
        );
    }

    public function testTheServiceAlsoRefusesRowanAndSaysSoKindly(): void
    {
        $bioService = new CreatureBioService($this->creatures, Guards::textGuard([]), 500);

        $result = $bioService->updateBio(
            $this->creatures->findById($this->mirasCreatureId),
            $this->rowanId,
            'I was here.'
        );

        $this->assertFalse($result->isSuccessful());
        // The refusal explains itself rather than failing silently (golden rule 4).
        $this->assertNotSame('', $result->message());
        $this->assertNull($this->creatures->findById($this->mirasCreatureId)->bio);
    }

    public function testMiraCanStillWriteOnHerOwnCreature(): void
    {
        // The guard above is only worth having if it lets the right person past.
        $this->creatures->updateBio($this->mirasCreatureId, $this->miraId, 'Loves naps in the sun.');

        $this->assertSame(
            'Loves naps in the sun.',
            $this->creatures->findById($this->mirasCreatureId)->bio
        );
    }

    public function testRowanDoesNotSeeMirasCreaturesInHisCollection(): void
    {
        $this->assertSame([], $this->creatures->findByOwner($this->rowanId));
        $this->assertCount(1, $this->creatures->findByOwner($this->miraId));
    }

    public function testRowanDoesNotSeeMirasBelongings(): void
    {
        $this->inventory->addItem($this->miraId, $this->itemId);

        $this->assertSame([], $this->inventory->findForUser($this->rowanId));
        $this->assertCount(1, $this->inventory->findForUser($this->miraId));
    }

    public function testTwoPlayersOwningTheSameKindOfThingKeepSeparatePiles(): void
    {
        // Items are counted rather than individually identified, so the pile has
        // to belong to a person. If the count were ever stored per item instead of
        // per person-and-item, this is the test that would notice.
        $this->inventory->addItem($this->miraId, $this->itemId);
        $this->inventory->addItem($this->miraId, $this->itemId);
        $this->inventory->addItem($this->rowanId, $this->itemId);

        $this->assertSame(2, $this->inventory->findForUser($this->miraId)[0]->quantity);
        $this->assertSame(1, $this->inventory->findForUser($this->rowanId)[0]->quantity);
    }
}
