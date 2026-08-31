<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Economy\Item;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Tests\Support\Pictures;
use Felkyo\Users\User;

/**
 * The item and shop editors: what they build, and everything they refuse.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.4 plan's promises as tests: a draft item is on no shelf and cannot
 * be bought; the margin rule refuses AT SAVE; seasonal dates are honoured
 * by the real listing and purchase paths; a draft cannot be shelved; the
 * keeper's words reach the real shop page; hostile names render inert;
 * revert restores — shelves included.
 *
 * FIXTURES: the seeded items and the general store are shared by the whole
 * suite, so these tests never mutate them — each creates its own uniquely
 * named things and asserts about those. (The one exception reads the
 * general store but changes nothing.)
 */
final class ContentEditorsTest extends DatabaseTestCase
{
    private AdminWorld $world;
    private User $artist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'art_files', 'admin_recovery_codes', 'admin_second_factors',
            'admin_audit_log', 'user_roles', 'pettings', 'creatures', 'users'
        );
        $_SESSION = [];
        $this->world = AdminWorld::build($this->connection);
        $this->artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($this->artist);
        $this->world->passDoorFor($this->artist);
    }

    protected function tearDown(): void
    {
        $this->world->cleanUpArtFolder();
        parent::tearDown();
    }

    private function freshName(string $prefix): string
    {
        return $prefix . ' ' . substr(uniqid(), -8);
    }

    private function post(string $path, array $fields): Response
    {
        $fields['_csrf_token'] = $this->world->csrf->token();

        return $this->world->router->dispatch(new Request('POST', $path, $fields, '127.0.0.1'));
    }

    /** Create a draft item through the real route and return it. */
    private function createDraftItem(?string $name = null): Item
    {
        $categoryId = $this->world->itemRepo->allCategories()[0]->id;
        $response = $this->post('/admin/items/create', [
            'name' => $name ?? $this->freshName('Testberry'),
            'category_id' => (string) $categoryId,
        ]);
        preg_match('~/admin/items/(\d+)~', (string) $response->header('Location'), $match);
        $item = $this->world->itemRepo->findById((int) ($match[1] ?? 0));
        $this->assertNotNull($item, 'creating an item must land on its editor page');

        return $item;
    }

    /** Give an item a price and publish it. */
    private function publishItem(Item $item, int $price = 10): Item
    {
        $this->post('/admin/items/' . $item->id . '/numbers', [
            'price' => (string) $price, 'sell_value' => '0',
            'happiness_bonus' => '0', 'energy_bonus' => '0',
            'category_id' => (string) $item->category->id,
        ]);
        $this->post('/admin/items/' . $item->id . '/publish', []);

        return $this->world->itemRepo->findById($item->id);
    }

    private function generalStoreId(): int
    {
        return $this->world->shopRepo->findBySlug('general-store')->id;
    }

    // --- The margin rule, at save time ---

    public function testASellValueAboveTheMarginIsRefusedAtSave(): void
    {
        $item = $this->createDraftItem();

        $this->post('/admin/items/' . $item->id . '/numbers', [
            'price' => '10', 'sell_value' => '9', // 80% of 10 is 8 — 9 is too much
            'happiness_bonus' => '0', 'energy_bonus' => '0',
            'category_id' => (string) $item->category->id,
        ]);

        $this->assertStringContainsString('at most 8', $_SESSION['flash'] ?? '');
        $this->assertSame(0, $this->world->itemRepo->findById($item->id)->sellValue, 'nothing was saved');
    }

    // --- Draft items can neither be shelved, seen, nor bought ---

    public function testADraftItemCannotBeShelved(): void
    {
        $item = $this->createDraftItem();

        $this->post('/admin/shops/' . $this->generalStoreId() . '/shelf', [
            'item_id' => (string) $item->id, 'available_from' => '', 'available_to' => '',
        ]);

        $this->assertStringContainsString('publish the item first', $_SESSION['flash'] ?? '');
        $listedIds = array_column($this->world->shopRepo->listingsFor($this->generalStoreId()), 'item_id');
        $this->assertNotContains($item->id, $listedIds);
    }

    public function testAnItemTakenBackToDraftLeavesTheShelfAndCannotBeBought(): void
    {
        $item = $this->publishItem($this->createDraftItem());
        $storeId = $this->generalStoreId();
        $this->post('/admin/shops/' . $storeId . '/shelf', [
            'item_id' => (string) $item->id, 'available_from' => '', 'available_to' => '',
        ]);

        // On the shelf while published...
        $offered = array_map(static fn (Item $offer): int => $offer->id, $this->world->shopRepo->findItems($storeId));
        $this->assertContains($item->id, $offered);

        // ...and gone from BOTH the shelf and the purchase lookup as a draft.
        $this->post('/admin/items/' . $item->id . '/unpublish', []);
        $offered = array_map(static fn (Item $offer): int => $offer->id, $this->world->shopRepo->findItems($storeId));
        $this->assertNotContains($item->id, $offered, 'the shelf must not offer a draft');
        $this->assertNull($this->world->shopRepo->findSoldItem($storeId, $item->id),
            'the purchase-time lookup must refuse a draft, however the request was built');
    }

    // --- The seasonal dates, honoured by the real queries ---

    public function testAListingOutsideItsSeasonIsSimplyNotOffered(): void
    {
        $item = $this->publishItem($this->createDraftItem());
        $storeId = $this->generalStoreId();

        // A season that starts tomorrow: listed, but not offered.
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->post('/admin/shops/' . $storeId . '/shelf', [
            'item_id' => (string) $item->id, 'available_from' => $tomorrow, 'available_to' => '',
        ]);

        $offered = array_map(static fn (Item $offer): int => $offer->id, $this->world->shopRepo->findItems($storeId));
        $this->assertNotContains($item->id, $offered, 'a listing before its season must not be offered');
        $this->assertNull($this->world->shopRepo->findSoldItem($storeId, $item->id));

        // Widen the season to today: offered.
        $this->post('/admin/shops/' . $storeId . '/shelf', [
            'item_id' => (string) $item->id, 'available_from' => date('Y-m-d'), 'available_to' => '',
        ]);
        $offered = array_map(static fn (Item $offer): int => $offer->id, $this->world->shopRepo->findItems($storeId));
        $this->assertContains($item->id, $offered);

        // Tidy the shared shelf.
        $this->post('/admin/shops/' . $storeId . '/shelf/' . $item->id . '/remove', []);
    }

    public function testASeasonEndingBeforeItStartsIsRefused(): void
    {
        $item = $this->publishItem($this->createDraftItem());

        $this->post('/admin/shops/' . $this->generalStoreId() . '/shelf', [
            'item_id' => (string) $item->id,
            'available_from' => '2027-03-01', 'available_to' => '2027-02-01',
        ]);

        $this->assertStringContainsString('swap the two dates', $_SESSION['flash'] ?? '');
    }

    // --- Pictures: the kind rule ---

    public function testAPictureOfTheWrongKindIsRefusedAsAnItemIcon(): void
    {
        $item = $this->createDraftItem();
        // Upload as SPRITE, then try to hang it on an item.
        $this->world->router->dispatch(new Request(
            'POST', '/admin/pictures/upload',
            ['_csrf_token' => $this->world->csrf->token(), 'kind' => 'sprite'],
            '127.0.0.1', [],
            ['picture' => Pictures::asUpload(Pictures::stillGif(), 'sprite.gif')]
        ));
        $sprite = $this->world->pictures->all()[0];

        $this->post('/admin/items/' . $item->id . '/icon', ['art_id' => (string) $sprite->id]);

        $this->assertStringContainsString('filed as', $_SESSION['flash'] ?? '');
        $this->assertNull($this->world->itemRepo->findById($item->id)->iconArtId);
    }

    // --- The keeper's words reach the real page ---

    public function testTheKeepersWordsAppearOnTheRealShopPage(): void
    {
        // The general store is shared: set the keeper fields to fresh values
        // and put them back afterwards, exactly as found.
        $store = $this->world->shopRepo->findBySlug('general-store');
        $greeting = 'Welcome in, ' . substr(uniqid(), -6) . '!';

        $this->post('/admin/shops/' . $store->id . '/keeper', [
            'keeper_name' => 'Ruinily',
            'keeper_greeting' => $greeting,
            'keeper_refusal' => 'Nothing of the sort here, dear.',
        ]);

        $reloaded = $this->world->shopRepo->findBySlug('general-store');
        $this->assertSame($greeting, $reloaded->keeperGreeting);
        $this->assertSame('Nothing of the sort here, dear.', $reloaded->refusalLine());

        // Put the row back as the migration seeded it.
        $this->post('/admin/shops/' . $store->id . '/keeper', [
            'keeper_name' => $store->keeperName ?? '',
            'keeper_greeting' => $store->keeperGreeting ?? '',
            'keeper_refusal' => $store->keeperRefusal ?? '',
        ]);
    }

    // --- Hostile words render inert ---

    public function testAScriptTagItemNameRendersInertOnItsEditorPage(): void
    {
        $item = $this->createDraftItem();
        $this->post('/admin/items/' . $item->id . '/words', [
            'name' => '<script>alert(1)</script>', 'description' => '',
        ]);

        $page = $this->world->router->dispatch(
            new Request('GET', '/admin/items/' . $item->id, [], '127.0.0.1')
        );

        $this->assertSame(200, $page->statusCode());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page->body());
    }

    // --- Versions, both editors ---

    public function testRevertRestoresAnItemsNumbers(): void
    {
        $item = $this->createDraftItem();
        $this->post('/admin/items/' . $item->id . '/numbers', [
            'price' => '10', 'sell_value' => '5',
            'happiness_bonus' => '0', 'energy_bonus' => '0',
            'category_id' => (string) $item->category->id,
        ]);
        $this->post('/admin/items/' . $item->id . '/numbers', [
            'price' => '40', 'sell_value' => '20',
            'happiness_bonus' => '0', 'energy_bonus' => '0',
            'category_id' => (string) $item->category->id,
        ]);

        $versions = $this->world->speciesVersions->listFor('item', $item->id);
        $target = null;
        foreach ($versions as $version) {
            if (($version['snapshot']['price'] ?? null) === 10) {
                $target = $version['id'];
                break;
            }
        }
        $this->assertNotNull($target);

        $this->post('/admin/items/' . $item->id . '/revert', ['version_id' => (string) $target]);

        $reloaded = $this->world->itemRepo->findById($item->id);
        $this->assertSame(10, $reloaded->price);
        $this->assertSame(5, $reloaded->sellValue);
    }

    public function testRevertRestoresAShopsShelvesToo(): void
    {
        // A fresh shop, so the shared store's shelf is never touched.
        $response = $this->post('/admin/shops/create', ['name' => $this->freshName('Teststall')]);
        preg_match('~/admin/shops/(\d+)~', (string) $response->header('Location'), $match);
        $shopId = (int) ($match[1] ?? 0);

        $item = $this->publishItem($this->createDraftItem());
        $this->post('/admin/shops/' . $shopId . '/shelf', [
            'item_id' => (string) $item->id, 'available_from' => '', 'available_to' => '',
        ]);

        // The version holding the stocked shelf, then empty the shelf.
        $versions = $this->world->speciesVersions->listFor('shop', $shopId);
        $stockedVersion = $versions[0]['id'];
        $this->post('/admin/shops/' . $shopId . '/shelf/' . $item->id . '/remove', []);
        $this->assertSame([], $this->world->shopRepo->listingsFor($shopId), 'the shelf is empty');

        // Restore: the listing comes back.
        $this->post('/admin/shops/' . $shopId . '/revert', ['version_id' => (string) $stockedVersion]);
        $listedIds = array_column($this->world->shopRepo->listingsFor($shopId), 'item_id');
        $this->assertContains($item->id, $listedIds, 'restoring a shop restores its shelves');
    }

    // --- The editor pages themselves ---

    public function testTheItemEditorNamesWhereAnItemIsWovenIn(): void
    {
        // acorn-treat is both a starter gift and exploration loot — the page
        // must say so (that is the whole answer to "why can't slugs change").
        $acorn = null;
        foreach ($this->world->itemRepo->all() as $item) {
            if ($item->slug === 'acorn-treat') {
                $acorn = $item;
                break;
            }
        }
        $this->assertNotNull($acorn, 'the seeded acorn-treat must exist');

        $page = $this->world->router->dispatch(
            new Request('GET', '/admin/items/' . $acorn->id, [], '127.0.0.1')
        );

        $this->assertStringContainsString('starter gift', $page->body());
    }
}
