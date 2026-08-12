<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\RegistrationService;
use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Http\Controllers\RegisterController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Safety\ImpersonationGuard;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\UserRepository;
use Felkyo\Users\UserValidator;
use League\Plates\Engine;

/**
 * Tests the switch that closes public registration (increment D.1).
 *
 * @package Felkyo\Tests\Integration
 *
 * WHY THIS MATTERS: the deployed Felkyo is a closed demo. If this switch ever
 * stopped working, the live site would quietly start accepting real sign-ups from
 * real people — which is exactly the responsibility the project has chosen not to
 * take on. So the flag is tested from both directions, and the POST is tested
 * separately from the GET: hiding a form does not stop anyone posting to its
 * address directly.
 */
final class RegistrationClosedTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('guestbook_entries', 'rate_limit_hits', 'pettings', 'creatures', 'users');
        $_SESSION = [];
    }

    /**
     * Build the registration route with the flag in the given position.
     */
    private function routerWithRegistrationOpen(bool $registrationOpen): Router
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $templates->registerFunction('csrf_field', static fn (): string => '');
        $session = new Session(cookieSecure: false);

        $users = new UserRepository($this->connection);
        $hasher = new PasswordHasher();

        $controller = new RegisterController(
            $templates,
            new Csrf($session),
            $session,
            new RegistrationService(
                $users, new UserValidator($config['security']), $hasher,
                Guards::textGuard(), new ImpersonationGuard(), 30
            ),
            new StarterCreatureService(
                new SpeciesRepository($this->connection),
                new CreatureRepository($this->connection),
                $config['gameplay']['creature_names']
            ),
            new RateLimiter(new RateLimitRepository($this->connection)),
            $config['security'],
            $registrationOpen
        );

        $router = new Router();
        $router->get('/register', [$controller, 'show']);
        $router->post('/register', [$controller, 'submit']);

        return $router;
    }

    private function postRegistration(Router $router): Response
    {
        // The CSRF token is deliberately valid here: this test is about the
        // registration switch, not about CSRF, and a wrong token would refuse the
        // request for the wrong reason and prove nothing.
        $session = new Session(cookieSecure: false);

        return $router->dispatch(new Request('POST', '/register', [
            '_csrf_token' => (new Csrf($session))->token(),
            'username' => 'newcomer',
            'email' => 'newcomer@example.com',
            'password' => 'a-long-enough-password',
        ], '127.0.0.1'));
    }

    private function countUsers(): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function testTheRegistrationFormIsRefusedWhenRegistrationIsClosed(): void
    {
        $response = $this->routerWithRegistrationOpen(false)
            ->dispatch(new Request('GET', '/register', [], '127.0.0.1'));

        $this->assertSame(403, $response->statusCode());
        $this->assertStringContainsString('closed for now', $response->body());
    }

    /**
     * The one that really counts: posting straight to the address, bypassing the
     * hidden form entirely, must not create an account.
     */
    public function testPostingToRegisterCreatesNoAccountWhenRegistrationIsClosed(): void
    {
        $response = $this->postRegistration($this->routerWithRegistrationOpen(false));

        $this->assertSame(403, $response->statusCode());
        $this->assertSame(0, $this->countUsers(), 'No account may be created while sign-ups are closed.');
    }

    public function testRegistrationStillWorksWhenTheFlagIsOpen(): void
    {
        $response = $this->postRegistration($this->routerWithRegistrationOpen(true));

        $this->assertSame(302, $response->statusCode());
        $this->assertSame(1, $this->countUsers());
    }

    public function testTheFormIsShownWhenRegistrationIsOpen(): void
    {
        $response = $this->routerWithRegistrationOpen(true)
            ->dispatch(new Request('GET', '/register', [], '127.0.0.1'));

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('name="username"', $response->body());
    }
}
