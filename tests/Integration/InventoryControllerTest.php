<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ShopRepository;
use Felkyo\Http\Controllers\InventoryController;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the inventory page through the router.
 *
 * @package Felkyo\Tests\Integration
 */
final class InventoryControllerTest extends DatabaseTestCase
{
    private Router $router;
    private InventoryRepository $inventory;
    private int $userId;
    private string $itemName;
    private int $itemIdToGive;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $templates->registerFunction('csrf_field', static fn (): string => '');
        $session = new Session(cookieSecure: false);

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $this->inventory = new InventoryRepository($this->connection);
        $controller = new InventoryController(
            $templates, $session, $this->inventory,
            new \Felkyo\Economy\ItemFinder(),
            $config['gameplay']['finder']['search_shown_from']
        );

        $this->router = new Router();
        $this->router->get('/inventory', [$controller, 'show']);

        $this->userId = (new UserRepository($this->connection))
            ->create('owner', 'owner@example.com', 'hash')->id;

        // A real seeded item to own.
        $shops = new ShopRepository($this->connection);
        $item = $shops->findItems($shops->findBySlug('general-store')->id)[0];
        $this->itemName = $item->name;
        $this->itemIdToGive = $item->id;
    }

    private function get(array $query = []): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request('GET', '/inventory', [], '127.0.0.1', $query));
    }

    public function testInventoryRequiresLogin(): void
    {
        $response = $this->get();

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testItShowsOwnedItems(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $this->inventory->addItem($this->userId, $this->itemIdToGive);

        $response = $this->get();

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString($this->itemName, $response->body());
    }

    public function testThePageCarriesTheNoReloadFilteringContract(): void
    {
        // item-finder.js filters in place by reading data-category/data-name
        // off every card, and only takes over when data-complete-list marks
        // the page as holding the whole list. If any of these disappear from
        // the markup, filtering silently falls back to full reloads — this
        // test is what makes that a red bar instead of a quiet regression.
        $_SESSION['user_id'] = $this->userId;
        // Two categories, because the finder row only renders when there is a
        // real choice to make — with one lone item there is nothing to filter.
        $this->giveOneOfEachCategory();

        $body = $this->get()->body();

        $this->assertStringContainsString('data-category=', $body);
        $this->assertStringContainsString('data-name=', $body);
        $this->assertStringContainsString('/js/item-finder.js', $body);

        // The unfiltered view holds everything, so in-place filtering may run…
        $this->assertStringContainsString('data-complete-list', $body);
        // …but a server-filtered view holds only a slice, so it must not.
        $this->assertStringNotContainsString(
            'data-complete-list',
            $this->get(['category' => 'dish'])->body()
        );
    }

    public function testAnEmptyInventoryShowsAnEmptyState(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->get();

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('don', $response->body()); // "don't own anything yet"
    }

    /** Give the owner one treat (dish) and one sticker, so filters have work to do. */
    private function giveOneOfEachCategory(): array
    {
        $shops = new ShopRepository($this->connection);
        $stock = $shops->findItems($shops->findBySlug('general-store')->id);

        $byCategory = [];
        foreach ($stock as $item) {
            if (!isset($byCategory[$item->category->slug])) {
                $byCategory[$item->category->slug] = $item;
                $this->inventory->addItem($this->userId, $item->id);
            }
        }

        return $byCategory; // slug => Item, one owned per category
    }

    public function testACategoryFilterShowsOnlyThatCategory(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $owned = $this->giveOneOfEachCategory();

        $response = $this->get(['category' => 'dish']);

        $this->assertStringContainsString($owned['dish']->name, $response->body());
        $this->assertStringNotContainsString($owned['sticker']->name, $response->body());
        $this->assertStringContainsString('Showing 1 of 2', $response->body());
    }

    public function testASearchFindsByPartOfTheName(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $this->inventory->addItem($this->userId, $this->itemIdToGive);

        // Part of the name, wrong case — a player half-remembering, not quoting.
        $fragment = strtoupper(substr($this->itemName, 2, 4));
        $response = $this->get(['q' => $fragment]);

        $this->assertStringContainsString($this->itemName, $response->body());
    }

    public function testASearchWithNoHitsIsNotADeadEnd(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $this->inventory->addItem($this->userId, $this->itemIdToGive);

        $response = $this->get(['q' => 'zzz-nothing']);

        $this->assertStringContainsString('Nothing of yours matches', $response->body());
        $this->assertStringContainsString('Show everything', $response->body());
    }

    public function testAnUnknownCategorySimplyShowsEverything(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $this->inventory->addItem($this->userId, $this->itemIdToGive);

        $response = $this->get(['category' => 'no-such-thing']);

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString($this->itemName, $response->body());
    }

    public function testAnotherPlayersThingsNeverAppearWhateverTheFilter(): void
    {
        // The other player owns a sticker; the signed-in owner owns nothing.
        // No filter combination may surface someone else's things.
        $otherId = (new UserRepository($this->connection))
            ->create('someone-else', 'else@example.com', 'hash')->id;

        $shops = new ShopRepository($this->connection);
        $sticker = null;
        foreach ($shops->findItems($shops->findBySlug('general-store')->id) as $item) {
            if ($item->category->slug === 'sticker') {
                $sticker = $item;
            }
        }
        $this->inventory->addItem($otherId, $sticker->id);

        $_SESSION['user_id'] = $this->userId;

        // Checked as "no item card at all" rather than "name absent", because a
        // search for the sticker's name legitimately echoes that name back in
        // the search box — what must never appear is the item itself.
        foreach ([[], ['category' => 'sticker'], ['q' => $sticker->name]] as $query) {
            $this->assertStringNotContainsString('item-card', $this->get($query)->body());
        }
    }

    public function testHostileSearchTextComesBackEscaped(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $this->inventory->addItem($this->userId, $this->itemIdToGive);

        $response = $this->get(['q' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        $this->assertStringContainsString('&lt;script&gt;', $response->body());
    }
}
