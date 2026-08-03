<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\AdoptionService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\AdoptionController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the adoption page and action through the router.
 *
 * @package Felkyo\Tests\Integration
 */
final class AdoptionControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private CreatureRepository $creatures;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $templates->registerFunction('csrf_field', function (): string {
            return '<input type="hidden" name="_csrf_token" value="'
                . htmlspecialchars($this->csrf->token(), ENT_QUOTES, 'UTF-8') . '">';
        });

        $this->creatures = new CreatureRepository($this->connection);
        $users = new UserRepository($this->connection);
        $adoption = new AdoptionService(
            new SpeciesRepository($this->connection),
            $this->creatures,
            $users,
            ['cooldown_seconds' => 86400, 'names' => ['Pip', 'Biscuit']]
        );
        $rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));

        $controller = new AdoptionController(
            $templates, $session, $this->csrf, $adoption, $rateLimiter,
            $config['security']['rate_limit_adopt']
        );

        $this->router = new Router();
        $this->router->get('/adopt', [$controller, 'show']);
        $this->router->post('/adopt', [$controller, 'adopt']);

        $this->userId = $users->create('adopter', 'adopter@example.com', 'hash')->id;
    }

    private function postAdopt(string $token): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request('POST', '/adopt', ['_csrf_token' => $token], '127.0.0.1'));
    }

    public function testAdoptingSendsThePlayerToTheirNewCreature(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->postAdopt($this->csrf->token());

        $this->assertSame(302, $response->statusCode());
        $this->assertStringStartsWith('/creature/', (string) $response->header('Location'));
        $this->assertCount(1, $this->creatures->findByOwner($this->userId));
    }

    public function testASecondAdoptionTheSameDayIsRefused(): void
    {
        $_SESSION['user_id'] = $this->userId;
        $token = $this->csrf->token();

        $this->postAdopt($token);
        $second = $this->postAdopt($token);

        // Sent back to the adopt page, and still only one creature.
        $this->assertSame(302, $second->statusCode());
        $this->assertSame('/adopt', $second->header('Location'));
        $this->assertCount(1, $this->creatures->findByOwner($this->userId));
    }

    public function testAGuestCannotAdopt(): void
    {
        $response = $this->postAdopt($this->csrf->token());

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
        $this->assertCount(0, $this->creatures->findByOwner($this->userId));
    }

    public function testTheAdoptPageRequiresLogin(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/adopt', [], '127.0.0.1'));

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }
}
