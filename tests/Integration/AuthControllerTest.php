<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Authenticator;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\RegistrationService;
use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Http\Controllers\LoginController;
use Felkyo\Http\Controllers\LogoutController;
use Felkyo\Http\Controllers\RegisterController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use Felkyo\Users\UserValidator;
use League\Plates\Engine;

/**
 * Tests the account controllers through the real path: router -> controller ->
 * repository -> database. No web server is involved; we build a Request by hand,
 * dispatch it through the Router, and inspect the returned Response.
 *
 * @package Felkyo\Tests\Integration
 */
final class AuthControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Session $session;
    private Csrf $csrf;
    private UserRepository $users;
    private RegistrationService $registration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'pettings', 'creatures', 'users');

        // A fresh, empty session for each test (an array, since there is no real
        // web session in a test).
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $security = $config['security'];

        // Templating, with the same csrf_field() helper the website registers.
        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $this->session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($this->session);
        $templates->registerFunction('csrf_field', function (): string {
            return '<input type="hidden" name="_csrf_token" value="'
                . htmlspecialchars($this->csrf->token(), ENT_QUOTES, 'UTF-8') . '">';
        });

        // Repositories and services.
        $this->users = new UserRepository($this->connection);
        $hasher = new PasswordHasher();
        $validator = new UserValidator($security);
        $this->registration = new RegistrationService($this->users, $validator, $hasher);
        $authenticator = new Authenticator($this->users, $hasher);
        $rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));

        // Registration now also grants a starter creature (from the seeded species).
        $starterCreatures = new StarterCreatureService(
            new SpeciesRepository($this->connection),
            new CreatureRepository($this->connection),
            $config['gameplay']['starter_creature_names']
        );

        // Controllers.
        $registerController = new RegisterController(
            $templates, $this->csrf, $this->session, $this->registration, $starterCreatures, $rateLimiter, $security
        );
        $loginController = new LoginController(
            $templates, $this->csrf, $this->session, $authenticator, $this->users, $rateLimiter, $security
        );
        $logoutController = new LogoutController($this->session, $this->csrf);

        // Routes, wired exactly as the front controller wires them.
        $this->router = new Router();
        $this->router->get('/register', [$registerController, 'show']);
        $this->router->post('/register', [$registerController, 'submit']);
        $this->router->get('/login', [$loginController, 'show']);
        $this->router->post('/login', [$loginController, 'submit']);
        $this->router->post('/logout', [$logoutController, 'submit']);
    }

    /** Build a request, defaulting the IP to a fixed test address. */
    private function request(string $method, string $path, array $post = []): Request
    {
        return new Request($method, $path, $post, '127.0.0.1');
    }

    /** The valid CSRF token for the current session (as a form would carry). */
    private function validToken(): string
    {
        return $this->csrf->token();
    }

    // --- Registration ---

    public function testTheRegistrationFormIsShown(): void
    {
        $response = $this->router->dispatch($this->request('GET', '/register'));

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('Create an account', $response->body());
    }

    public function testASuccessfulRegistrationLogsInAndRedirectsHome(): void
    {
        $response = $this->router->dispatch($this->request('POST', '/register', [
            '_csrf_token' => $this->validToken(),
            'username' => 'biscuit',
            'email' => 'biscuit@example.com',
            'password' => 'a-good-password',
        ]));

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/', $response->header('Location'));

        // The new user exists and is now logged in (their id is in the session).
        $created = $this->users->findByUsername('biscuit');
        $this->assertNotNull($created);
        $this->assertSame($created->id, $_SESSION['user_id'] ?? null);
    }

    public function testRegistrationWithoutAValidCsrfTokenIsRejected(): void
    {
        $response = $this->router->dispatch($this->request('POST', '/register', [
            '_csrf_token' => 'not-the-real-token',
            'username' => 'biscuit',
            'email' => 'biscuit@example.com',
            'password' => 'a-good-password',
        ]));

        $this->assertSame(400, $response->statusCode());
        $this->assertNull($this->users->findByUsername('biscuit'));
    }

    public function testRegistrationWithInvalidInputShowsErrors(): void
    {
        $response = $this->router->dispatch($this->request('POST', '/register', [
            '_csrf_token' => $this->validToken(),
            'username' => 'biscuit',
            'email' => 'biscuit@example.com',
            'password' => 'short',
        ]));

        $this->assertSame(422, $response->statusCode());
        $this->assertStringContainsString('at least', $response->body());
        $this->assertNull($this->users->findByUsername('biscuit'));
    }

    public function testRegistrationWithATakenUsernameIsRejected(): void
    {
        $this->registration->register('biscuit', 'first@example.com', 'a-good-password');

        $response = $this->router->dispatch($this->request('POST', '/register', [
            '_csrf_token' => $this->validToken(),
            'username' => 'biscuit',
            'email' => 'second@example.com',
            'password' => 'a-good-password',
        ]));

        $this->assertSame(422, $response->statusCode());
        $this->assertStringContainsString('already taken', $response->body());
    }

    // --- Login / logout ---

    public function testLoggingInWithCorrectCredentialsRedirectsHome(): void
    {
        $this->registration->register('biscuit', 'biscuit@example.com', 'a-good-password');
        $_SESSION = []; // registration logs you in; start this test logged out

        $response = $this->router->dispatch($this->request('POST', '/login', [
            '_csrf_token' => $this->validToken(),
            'username' => 'biscuit',
            'password' => 'a-good-password',
        ]));

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/', $response->header('Location'));
        $this->assertArrayHasKey('user_id', $_SESSION);
    }

    public function testLoggingInWithTheWrongPasswordIsRefused(): void
    {
        $this->registration->register('biscuit', 'biscuit@example.com', 'a-good-password');
        $_SESSION = [];

        $response = $this->router->dispatch($this->request('POST', '/login', [
            '_csrf_token' => $this->validToken(),
            'username' => 'biscuit',
            'password' => 'the-wrong-password',
        ]));

        $this->assertSame(401, $response->statusCode());
        $this->assertStringContainsString('incorrect', $response->body());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testTooManyFailedLoginsAreBlocked(): void
    {
        $this->registration->register('biscuit', 'biscuit@example.com', 'a-good-password');
        $_SESSION = [];

        // The recommended policy allows 5 failed attempts; make exactly 5.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $failed = $this->router->dispatch($this->request('POST', '/login', [
                '_csrf_token' => $this->validToken(),
                'username' => 'biscuit',
                'password' => 'the-wrong-password',
            ]));
            $this->assertSame(401, $failed->statusCode());
        }

        // The 6th attempt is blocked before the password is even checked.
        $blocked = $this->router->dispatch($this->request('POST', '/login', [
            '_csrf_token' => $this->validToken(),
            'username' => 'biscuit',
            'password' => 'the-wrong-password',
        ]));

        $this->assertSame(429, $blocked->statusCode());
        $this->assertStringContainsString('Too many', $blocked->body());
    }

    public function testLoggingOutClearsTheSession(): void
    {
        // Log in first by registering through the controller (which logs you in).
        $this->router->dispatch($this->request('POST', '/register', [
            '_csrf_token' => $this->validToken(),
            'username' => 'biscuit',
            'email' => 'biscuit@example.com',
            'password' => 'a-good-password',
        ]));
        $this->assertArrayHasKey('user_id', $_SESSION);

        $response = $this->router->dispatch($this->request('POST', '/logout', [
            '_csrf_token' => $this->validToken(),
        ]));

        $this->assertSame(302, $response->statusCode());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
}
