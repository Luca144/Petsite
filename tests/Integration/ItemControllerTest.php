<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ItemDisposalService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Http\Controllers\ItemController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the item page and its two actions through the router.
 *
 * @package Felkyo\Tests\Integration
 *
 * This exercises the real path — router to controller to service to database —
 * so the guards that live in the controller (login, CSRF) are proved where they
 * actually run, rather than assumed because the service below them is safe.
 */
final class ItemControllerTest extends DatabaseTestCase
{
    private Router $router;
    private InventoryRepository $inventory;
    private UserRepository $users;
    private int $miraId;
    private int $rowanId;
    private int $itemId;
    private string $itemName;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users', 'rate_limit_hits');
        $_SESSION = [];

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $templates->registerFunction('csrf_field', static fn (): string => '');
        $session = new Session(cookieSecure: false);

        $this->users = new UserRepository($this->connection);
        $this->inventory = new InventoryRepository($this->connection);

        $controller = new ItemController(
            $templates,
            $session,
            new Csrf($session),
            $this->inventory,
            new ItemDisposalService($this->connection, $this->users, $this->inventory),
            new RateLimiter(new RateLimitRepository($this->connection)),
            ['max_attempts' => 30, 'window_seconds' => 60]
        );

        $this->router = new Router();
        $this->router->get('/inventory/{id}', [$controller, 'show']);
        $this->router->post('/inventory/{id}/sell', [$controller, 'sell']);
        $this->router->post('/inventory/{id}/discard', [$controller, 'discard']);

        $this->miraId = $this->users->create('mira', 'mira@example.com', 'hash')->id;
        $this->rowanId = $this->users->create('rowan', 'rowan@example.com', 'hash')->id;

        $shops = new ShopRepository($this->connection);
        $item = $shops->findItems($shops->findBySlug('general-store')->id)[0];
        $this->itemId = $item->id;
        $this->itemName = $item->name;
    }

    private function view(int $itemId): Response
    {
        return $this->router->dispatch(new Request('GET', '/inventory/' . $itemId, [], '127.0.0.1'));
    }

    private function post(string $path, array $input): Response
    {
        return $this->router->dispatch(new Request('POST', $path, $input, '127.0.0.1'));
    }

    /**
     * The token a real form would carry. Csrf reads and writes the session, so
     * asking it for the token here is the same thing the template does.
     */
    private function validToken(): string
    {
        return (new Csrf(new Session(cookieSecure: false)))->token();
    }

    public function testTheItemPageRequiresLogin(): void
    {
        $response = $this->view($this->itemId);

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testTheOwnerSeesTheirItem(): void
    {
        $_SESSION['user_id'] = $this->miraId;
        $this->inventory->addItem($this->miraId, $this->itemId);

        $response = $this->view($this->itemId);

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString($this->itemName, $response->body());
    }

    public function testSomebodyWhoDoesNotOwnItIsSentAway(): void
    {
        $_SESSION['user_id'] = $this->rowanId;
        $this->inventory->addItem($this->miraId, $this->itemId);

        $response = $this->view($this->itemId);

        // Sent back to their own things — and told the same thing they would be
        // told about an item that does not exist at all, so guessing id numbers
        // reveals nothing about what other players own.
        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/inventory', $response->header('Location'));
    }

    public function testSellingWithoutAValidTokenChangesNothing(): void
    {
        $_SESSION['user_id'] = $this->miraId;
        $this->inventory->addItem($this->miraId, $this->itemId);
        $before = $this->users->findById($this->miraId)->currencyBalance;

        $this->post('/inventory/' . $this->itemId . '/sell', ['_csrf_token' => 'not-the-real-token']);

        $this->assertSame(1, $this->inventory->findStackForUser($this->miraId, $this->itemId)->quantity);
        $this->assertSame($before, $this->users->findById($this->miraId)->currencyBalance);
    }

    public function testTheOwnerCanSellAndIsPaid(): void
    {
        $_SESSION['user_id'] = $this->miraId;
        $this->inventory->addItem($this->miraId, $this->itemId);
        $this->inventory->addItem($this->miraId, $this->itemId);
        $before = $this->users->findById($this->miraId)->currencyBalance;

        $response = $this->post(
            '/inventory/' . $this->itemId . '/sell',
            ['_csrf_token' => $this->validToken()]
        );

        $this->assertSame(302, $response->statusCode());
        $this->assertGreaterThan($before, $this->users->findById($this->miraId)->currencyBalance);
        $this->assertSame(1, $this->inventory->findStackForUser($this->miraId, $this->itemId)->quantity);
    }

    public function testSellingTheLastOneReturnsToTheInventoryRatherThanAMissingPage(): void
    {
        $_SESSION['user_id'] = $this->miraId;
        $this->inventory->addItem($this->miraId, $this->itemId);

        $response = $this->post(
            '/inventory/' . $this->itemId . '/sell',
            ['_csrf_token' => $this->validToken()]
        );

        // Going back to the item page would greet them with "you don't own one of
        // those" immediately after a perfectly successful sale.
        $this->assertSame('/inventory', $response->header('Location'));
    }

    public function testRowanCannotSellMirasItemThroughTheRoute(): void
    {
        $_SESSION['user_id'] = $this->rowanId;
        $this->inventory->addItem($this->miraId, $this->itemId);
        $rowansBalance = $this->users->findById($this->rowanId)->currencyBalance;

        $this->post('/inventory/' . $this->itemId . '/sell', ['_csrf_token' => $this->validToken()]);

        $this->assertSame($rowansBalance, $this->users->findById($this->rowanId)->currencyBalance);
        $this->assertSame(1, $this->inventory->findStackForUser($this->miraId, $this->itemId)->quantity);
    }

    public function testTheOwnerCanThrowSomethingAway(): void
    {
        $_SESSION['user_id'] = $this->miraId;
        $this->inventory->addItem($this->miraId, $this->itemId);

        $this->post('/inventory/' . $this->itemId . '/discard', ['_csrf_token' => $this->validToken()]);

        $this->assertNull($this->inventory->findStackForUser($this->miraId, $this->itemId));
    }
}
