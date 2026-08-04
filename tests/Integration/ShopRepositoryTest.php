<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Economy\ShopRepository;
use Felkyo\Tests\DatabaseTestCase;

/**
 * Tests for ShopRepository — reading the seeded shop and its stock.
 *
 * @package Felkyo\Tests\Integration
 *
 * These rely on the shop and items seeded by the migration (reference content the
 * app always needs, so they are not cleared).
 */
final class ShopRepositoryTest extends DatabaseTestCase
{
    private ShopRepository $shops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shops = new ShopRepository($this->connection);
    }

    public function testFindBySlugReturnsTheShop(): void
    {
        $shop = $this->shops->findBySlug('general-store');

        $this->assertNotNull($shop);
        $this->assertSame('general-store', $shop->slug);
    }

    public function testFindBySlugReturnsNullForAnUnknownShop(): void
    {
        $this->assertNull($this->shops->findBySlug('no-such-shop'));
    }

    public function testFindItemsReturnsTheStock(): void
    {
        $shop = $this->shops->findBySlug('general-store');

        $items = $this->shops->findItems($shop->id);

        $this->assertGreaterThanOrEqual(4, count($items));
    }

    public function testFindSoldItemOnlyReturnsItemsThisShopSells(): void
    {
        $shop = $this->shops->findBySlug('general-store');
        $anItem = $this->shops->findItems($shop->id)[0];

        // An item the shop sells is found...
        $this->assertNotNull($this->shops->findSoldItem($shop->id, $anItem->id));
        // ...an item id that is not on sale here is not.
        $this->assertNull($this->shops->findSoldItem($shop->id, 999999));
    }
}
