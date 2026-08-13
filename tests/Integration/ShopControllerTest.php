<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\PurchaseService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Http\Controllers\ShopController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the shop page and the buy action through the router.
 *
 * @package Felkyo\Tests\Integration
 */
final class ShopControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private UserRepository $users;
    private InventoryRepository $inventory;
    private int $userId;
    private int $itemId;
    private int $itemPrice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'inventory', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $templates->registerFunction('csrf_field', function (): string {
            return '<input type="hidden" name="_csrf_token" value="'
                . htmlspecialchars($this->csrf->token(), ENT_QUOTES, 'UTF-8') . '">';
        });

        $shops = new ShopRepository($this->connection);
        $this->users = new UserRepository($this->connection);
        $this->inventory = new InventoryRepository($this->connection);
        $purchase = new PurchaseService($this->connection, $shops, $this->users, $this->inventory);
        $rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));

        $controller = new ShopController(
            $templates, $session, $this->csrf, $shops, $purchase, $rateLimiter,
            new \Felkyo\Economy\ItemFinder(),
            [
                'slug' => 'general-store',
                'rate_limit' => $config['security']['rate_limit_purchase'],
                'search_shown_from' => $config['gameplay']['finder']['search_shown_from'],
            ]
        );

        $this->router = new Router();
        $this->router->get('/shop', [$controller, 'show']);
        $this->router->post('/shop/buy', [$controller, 'buy']);

        $this->userId = $this->users->create('buyer', 'buyer@example.com', 'hash')->id;
        $cheapest = $shops->findItems($shops->findBySlug('general-store')->id)[0];
        $this->itemId = $cheapest->id;
        $this->itemPrice = $cheapest->price;
    }

    private function buy(string $token): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/shop/buy',
            ['_csrf_token' => $token, 'item_id' => (string) $this->itemId],
            '127.0.0.1'
        ));
    }

    private function balance(): int
    {
        return $this->users->findById($this->userId)->currencyBalance;
    }

    public function testTheShopRequiresLogin(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/shop', [], '127.0.0.1'));

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testTheShopShowsItsStockWhenLoggedIn(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->router->dispatch(new Request('GET', '/shop', [], '127.0.0.1'));

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('Village Store', $response->body());
    }

    public function testACategoryFilterNarrowsTheStock(): void
    {
        $_SESSION['user_id'] = $this->userId;

        // The seed stocks treats (dish) and stickers; filtering to stickers must
        // drop the treats and say what happened.
        $response = $this->router->dispatch(new Request(
            'GET', '/shop', [], '127.0.0.1', ['category' => 'sticker']
        ));

        $this->assertStringNotContainsString('Acorn Treat', $response->body());
        $this->assertStringContainsString('Sticker', $response->body());
        $this->assertStringContainsString('Showing', $response->body());
    }

    public function testASearchWithNoHitsOffersTheWayBack(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->router->dispatch(new Request(
            'GET', '/shop', [], '127.0.0.1', ['q' => 'zzz-nothing']
        ));

        // Never a dead end: the empty shelf explains itself and links back.
        $this->assertStringContainsString('anything like that', $response->body());
        $this->assertStringContainsString('Show everything', $response->body());
    }

    public function testHostileSearchTextComesBackEscaped(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->router->dispatch(new Request(
            'GET', '/shop', [], '127.0.0.1', ['q' => '<script>alert(1)</script>']
        ));

        // The text is echoed into the search box and the page — escaped, only.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        $this->assertStringContainsString('&lt;script&gt;', $response->body());
    }

    public function testBuyingDeductsCurrencyAndAddsTheItem(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $this->users->addCurrency($this->userId, 100);

        $response = $this->buy($this->csrf->token());

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/shop', $response->header('Location'));
        $this->assertSame(100 - $this->itemPrice, $this->balance());
        $this->assertCount(1, $this->inventory->findForUser($this->userId));
    }

    public function testBuyingSomethingTooExpensiveIsRefused(): void
    {
        $_SESSION['user_id'] = $this->userId;
        // No currency at all.

        $this->buy($this->csrf->token());

        $this->assertSame(0, $this->balance());
        $this->assertCount(0, $this->inventory->findForUser($this->userId));
    }

    public function testBuyingRequiresLogin(): void
    {
        $response = $this->buy($this->csrf->token());

        $this->assertSame('/login', $response->header('Location'));
        $this->assertCount(0, $this->inventory->findForUser($this->userId));
    }

    public function testBuyingWithoutAValidCsrfTokenBuysNothing(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $this->users->addCurrency($this->userId, 100);

        $this->buy('wrong-token');

        $this->assertSame(100, $this->balance());
        $this->assertCount(0, $this->inventory->findForUser($this->userId));
    }
}
