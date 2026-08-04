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

        $this->inventory = new InventoryRepository($this->connection);
        $controller = new InventoryController($templates, $session, $this->inventory);

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

    private function get(): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request('GET', '/inventory', [], '127.0.0.1'));
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

    public function testAnEmptyInventoryShowsAnEmptyState(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->get();

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('don', $response->body()); // "don't own anything yet"
    }
}
