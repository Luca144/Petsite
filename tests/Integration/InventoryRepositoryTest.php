<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ShopRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for InventoryRepository — what a user owns.
 *
 * @package Felkyo\Tests\Integration
 */
final class InventoryRepositoryTest extends DatabaseTestCase
{
    private InventoryRepository $inventory;
    private int $userId;
    private int $firstItemId;
    private int $secondItemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $this->inventory = new InventoryRepository($this->connection);
        $this->userId = (new UserRepository($this->connection))
            ->create('owner', 'owner@example.com', 'hash')->id;

        // Use two real seeded items.
        $items = (new ShopRepository($this->connection))
            ->findItems((new ShopRepository($this->connection))->findBySlug('general-store')->id);
        $this->firstItemId = $items[0]->id;
        $this->secondItemId = $items[1]->id;
    }

    public function testAddingAnItemCreatesItThenIncrementsTheQuantity(): void
    {
        $this->inventory->addItem($this->userId, $this->firstItemId);
        $owned = $this->inventory->findForUser($this->userId);
        $this->assertCount(1, $owned);
        $this->assertSame(1, $owned[0]['quantity']);

        // Adding the same item again raises the quantity rather than adding a row.
        $this->inventory->addItem($this->userId, $this->firstItemId);
        $owned = $this->inventory->findForUser($this->userId);
        $this->assertCount(1, $owned);
        $this->assertSame(2, $owned[0]['quantity']);
    }

    public function testFindForUserReturnsEachDistinctItemOwned(): void
    {
        $this->inventory->addItem($this->userId, $this->firstItemId);
        $this->inventory->addItem($this->userId, $this->secondItemId);

        $this->assertCount(2, $this->inventory->findForUser($this->userId));
    }
}
