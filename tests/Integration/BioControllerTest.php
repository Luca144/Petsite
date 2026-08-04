<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\ContentFilter;
use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\BioController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests the bio-editing action through the router, especially the rule that only
 * the owner may edit.
 *
 * @package Felkyo\Tests\Integration
 */
final class BioControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private CreatureRepository $creatures;
    private int $ownerId;
    private int $strangerId;
    private int $creatureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $this->creatures = new CreatureRepository($this->connection);

        $bioService = new CreatureBioService(
            $this->creatures,
            new ContentFilter($config['moderation']['blocked_words']),
            $config['gameplay']['bio_max_length']
        );
        $rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));

        $controller = new BioController(
            $session, $this->csrf, $this->creatures, $bioService, $rateLimiter,
            $config['security']['rate_limit_bio']
        );

        $this->router = new Router();
        $this->router->post('/creature/{id}/bio', [$controller, 'update']);

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->strangerId = $users->create('stranger', 'stranger@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    private function postBio(string $token, string $bio): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/creature/' . $this->creatureId . '/bio',
            ['_csrf_token' => $token, 'bio' => $bio],
            '127.0.0.1'
        ));
    }

    private function currentBio(): ?string
    {
        return $this->creatures->findById($this->creatureId)->bio;
    }

    public function testTheOwnerCanSaveABio(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->postBio($this->csrf->token(), 'My lovely creature.');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('My lovely creature.', $this->currentBio());
    }

    public function testSomeoneWhoIsNotTheOwnerCannotEditTheBio(): void
    {
        // The stranger is logged in, but does not own the creature.
        $_SESSION['user_id'] = $this->strangerId;

        $this->postBio($this->csrf->token(), 'I should not be able to write this.');

        // The bio was not changed.
        $this->assertNull($this->currentBio());
    }

    public function testEditingRequiresLogin(): void
    {
        $response = $this->postBio($this->csrf->token(), 'Anonymous edit.');

        $this->assertSame('/login', $response->header('Location'));
        $this->assertNull($this->currentBio());
    }

    public function testEditingWithoutAValidCsrfTokenChangesNothing(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $this->postBio('wrong-token', 'Sneaky edit.');

        $this->assertNull($this->currentBio());
    }

    public function testAnOverlongBioIsRejected(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $this->postBio($this->csrf->token(), str_repeat('a', 501));

        $this->assertNull($this->currentBio());
    }
}
