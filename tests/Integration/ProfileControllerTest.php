<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\ProfileController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\ProfileService;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests a player's page and its edit form through the router.
 *
 * @package Felkyo\Tests\Integration
 */
final class ProfileControllerTest extends DatabaseTestCase
{
    private Router $router;
    private CreatureRepository $creatures;
    private ProfileRepository $profiles;
    private int $miraId;
    private int $rowanId;
    private int $mirasCreatureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users', 'rate_limit_hits');
        $_SESSION = [];

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $templates->registerFunction('csrf_field', static fn (): string => '');
        $session = new Session(cookieSecure: false);

        $users = new UserRepository($this->connection);
        $this->profiles = new ProfileRepository($this->connection);
        $this->creatures = new CreatureRepository($this->connection);

        $avatars = new AvatarSet([
            'default' => ['name' => 'The wandering visitor', 'file' => 'default.png'],
        ]);
        $limits = ['max_about_length' => 300, 'max_featured_creatures' => 3];

        $controller = new ProfileController(
            $templates,
            $session,
            new Csrf($session),
            $this->profiles,
            new ProfileService(
                $this->profiles,
                $this->creatures,
                $avatars,
                Guards::textGuard(['spam']),
                $limits
            ),
            $this->creatures,
            new CreatureProfileBuilder(
                new SpeciesRepository($this->connection),
                $users,
                new GrowthCalculator(20, ['baby' => 1, 'juvenile' => 3, 'adult' => 6]),
                new PettingRepository($this->connection),
                $this->moodCalculator()
            ),
            $avatars,
            new RateLimiter(new RateLimitRepository($this->connection)),
            $limits,
            ['max_attempts' => 20, 'window_seconds' => 3600]
        );

        $this->router = new Router();
        $this->router->get('/player/{username}', [$controller, 'show']);
        $this->router->get('/profile/edit', [$controller, 'edit']);
        $this->router->post('/profile', [$controller, 'save']);

        $this->miraId = $users->create('mira', 'mira@example.com', 'hash')->id;
        $this->rowanId = $users->create('rowan', 'rowan@example.com', 'hash')->id;

        $speciesId = (new SpeciesRepository($this->connection))->findStarters()[0]->id;
        $this->mirasCreatureId = $this->creatures->create($this->miraId, $speciesId, 'Biscuit')->id;
    }

    private function view(string $username): Response
    {
        return $this->router->dispatch(new Request('GET', '/player/' . $username, [], '127.0.0.1'));
    }

    private function token(): string
    {
        return (new Csrf(new Session(cookieSecure: false)))->token();
    }

    public function testAProfileIsVisibleToSomebodyWhoIsNotLoggedIn(): void
    {
        // A profile is a public page — that is the whole point of having one.
        $response = $this->view('mira');

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('mira', $response->body());
        $this->assertStringContainsString('Biscuit', $response->body());
    }

    public function testAProfileNeverShowsAnEmailAddress(): void
    {
        $response = $this->view('mira');

        $this->assertStringNotContainsString('mira@example.com', $response->body());
    }

    public function testAnUnknownNameGetsAKindPageRatherThanACrash(): void
    {
        $response = $this->view('nobody-by-that-name');

        $this->assertSame(404, $response->statusCode());
        // Never a dead end: it offers somewhere to go.
        $this->assertStringContainsString('href="/browse"', $response->body());
    }

    public function testAPrivateCreatureIsNotShownToAVisitor(): void
    {
        $this->connection->exec('UPDATE creatures SET is_public = 0 WHERE id = ' . $this->mirasCreatureId);

        $response = $this->view('mira');

        $this->assertStringNotContainsString('Biscuit', $response->body());
    }

    public function testTheOwnerIsToldTheirPageIsThePublicView(): void
    {
        $_SESSION['user_id'] = $this->miraId;

        $response = $this->view('mira');

        $this->assertStringContainsString('how your page looks to everyone else', $response->body());
    }

    public function testAVisitorIsNotToldHowManyCreaturesAreHidden(): void
    {
        $this->connection->exec('UPDATE creatures SET is_public = 0 WHERE id = ' . $this->mirasCreatureId);
        $_SESSION['user_id'] = $this->rowanId;

        $response = $this->view('mira');

        $this->assertStringNotContainsString('private, so', $response->body());
    }

    public function testEditingRequiresLogin(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/profile/edit', [], '127.0.0.1'));

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testSavingWithoutAValidTokenChangesNothing(): void
    {
        $_SESSION['user_id'] = $this->miraId;

        $this->router->dispatch(new Request('POST', '/profile', [
            '_csrf_token' => 'wrong',
            'about' => 'Hello there',
            'avatar_key' => 'default',
        ], '127.0.0.1'));

        $this->assertNull($this->profiles->findById($this->miraId)->about);
    }

    public function testTheOwnerCanSaveTheirPage(): void
    {
        $_SESSION['user_id'] = $this->miraId;

        $response = $this->router->dispatch(new Request('POST', '/profile', [
            '_csrf_token' => $this->token(),
            'about' => 'Hello from Mira.',
            'avatar_key' => 'default',
            'featured' => [(string) $this->mirasCreatureId],
        ], '127.0.0.1'));

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('Hello from Mira.', $this->profiles->findById($this->miraId)->about);
        $this->assertSame([$this->mirasCreatureId], $this->creatures->findFeaturedIds($this->miraId));
    }

    public function testThereIsNoWayToSayWhoseProfileToEdit(): void
    {
        // Rowan submits Mira's id in every field name that might be believed. The
        // controller takes the player from the session and never from the request,
        // so none of it can have any effect.
        $_SESSION['user_id'] = $this->rowanId;

        $this->router->dispatch(new Request('POST', '/profile', [
            '_csrf_token' => $this->token(),
            'about' => 'Rowan was here',
            'avatar_key' => 'default',
            'user_id' => (string) $this->miraId,
            'id' => (string) $this->miraId,
            'username' => 'mira',
        ], '127.0.0.1'));

        $this->assertNull($this->profiles->findById($this->miraId)->about, "Mira's page was changed by Rowan.");
        $this->assertSame('Rowan was here', $this->profiles->findById($this->rowanId)->about);
    }
}
