<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\PurchaseService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for PurchaseService — buying an item validates, deducts and grants, and
 * can never leave a negative balance.
 *
 * @package Felkyo\Tests\Integration
 */
final class PurchaseServiceTest extends DatabaseTestCase
{
    private PurchaseService $purchase;
    private UserRepository $users;
    private InventoryRepository $inventory;
    private int $userId;
    private int $shopId;
    private int $itemId;
    private int $itemPrice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $shops = new ShopRepository($this->connection);
        $this->users = new UserRepository($this->connection);
        $this->inventory = new InventoryRepository($this->connection);
        $this->purchase = new PurchaseService($this->connection, $shops, $this->users, $this->inventory);

        $this->userId = $this->users->create('buyer', 'buyer@example.com', 'hash')->id;

        $shop = $shops->findBySlug('general-store');
        $this->shopId = $shop->id;
        $cheapest = $shops->findItems($this->shopId)[0]; // cheapest item
        $this->itemId = $cheapest->id;
        $this->itemPrice = $cheapest->price;
    }

    private function balance(): int
    {
        return $this->users->findById($this->userId)->currencyBalance;
    }

    public function testASuccessfulPurchaseDeductsCurrencyAndGrantsTheItem(): void
    {
        $this->users->addCurrency($this->userId, 100);

        $result = $this->purchase->buy($this->userId, $this->shopId, $this->itemId);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(100 - $this->itemPrice, $this->balance());
        $this->assertCount(1, $this->inventory->findForUser($this->userId));
    }

    public function testBuyingWithTooLittleCurrencyIsRefusedAndChangesNothing(): void
    {
        // One coin short of the price.
        $this->users->addCurrency($this->userId, $this->itemPrice - 1);

        $result = $this->purchase->buy($this->userId, $this->shopId, $this->itemId);

        $this->assertFalse($result->isSuccessful());
        // Balance is untouched (and never negative) and nothing was granted.
        $this->assertSame($this->itemPrice - 1, $this->balance());
        $this->assertCount(0, $this->inventory->findForUser($this->userId));
    }

    public function testBuyingAnItemThisShopDoesNotSellFails(): void
    {
        $this->users->addCurrency($this->userId, 100);

        $result = $this->purchase->buy($this->userId, $this->shopId, 999999);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(100, $this->balance());
    }

    public function testBuyingTheSameItemTwiceStacksAndDeductsEachTime(): void
    {
        $this->users->addCurrency($this->userId, 100);

        $this->purchase->buy($this->userId, $this->shopId, $this->itemId);
        $this->purchase->buy($this->userId, $this->shopId, $this->itemId);

        $this->assertSame(100 - (2 * $this->itemPrice), $this->balance());
        $owned = $this->inventory->findForUser($this->userId);
        $this->assertCount(1, $owned);
        $this->assertSame(2, $owned[0]['quantity']);
    }
}
