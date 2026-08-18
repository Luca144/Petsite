<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\PetController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests the pet action through the real path: router -> controller -> service ->
 * repository -> database, including the login and CSRF guards.
 *
 * @package Felkyo\Tests\Integration
 */
final class PetControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private CreatureRepository $creatures;
    private int $ownerId;
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

        $pettingService = new PettingService(
            $this->connection,
            new PettingRepository($this->connection),
            new \Felkyo\Creatures\CreatureInteractions($this->connection),
            new UserRepository($this->connection),
            $this->moodCalculator(),
            $config['gameplay']['petting'] + [
                'currency_per_pet' => $config['gameplay']['currency']['per_pet'],
                'currency_daily_cap' => $config['gameplay']['currency']['daily_cap'],
                'currency_cap_window_seconds' => $config['gameplay']['currency']['daily_cap_window_seconds'],
            ]
        );
        $rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));

        $controller = new PetController(
            $session, $this->csrf, $this->creatures, $pettingService, $rateLimiter,
            $config['security']['rate_limit_pet']
        );

        $this->router = new Router();
        $this->router->post('/creature/{id}/pet', [$controller, 'pet']);

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    private function postPet(string $token): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/creature/' . $this->creatureId . '/pet',
            ['_csrf_token' => $token],
            '127.0.0.1'
        ));
    }

    private function currentXp(): int
    {
        return $this->creatures->findById($this->creatureId)->xp;
    }

    public function testPettingWhileLoggedInRaisesStatsAndRedirectsBack(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->postPet($this->csrf->token());

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/creature/' . $this->creatureId, $response->header('Location'));
        $this->assertGreaterThan(0, $this->currentXp());
    }

    public function testAGuestCannotPetAndIsSentToLogin(): void
    {
        // Not logged in.
        $response = $this->postPet($this->csrf->token());

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
        $this->assertSame(0, $this->currentXp());
    }

    public function testPettingWithoutAValidCsrfTokenDoesNothing(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->postPet('not-the-real-token');

        $this->assertSame(302, $response->statusCode());
        // Back to the creature page, but no stats changed.
        $this->assertSame(0, $this->currentXp());
    }

    public function testTheCooldownBlocksAnImmediateSecondPet(): void
    {
        $_SESSION['user_id'] = $this->ownerId;
        $token = $this->csrf->token();

        $this->postPet($token);
        $xpAfterFirst = $this->currentXp();

        // A second pet straight away is refused by the cooldown, so XP is unchanged.
        $this->postPet($token);
        $this->assertSame($xpAfterFirst, $this->currentXp());
    }

    public function testASuccessfulPetAsksForTheHeartCelebration(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $this->postPet($this->csrf->token());

        $this->assertSame('pet', $_SESSION['celebrate'] ?? null);
    }

    public function testACooldownPetDoesNotCelebrate(): void
    {
        $_SESSION['user_id'] = $this->ownerId;
        $token = $this->csrf->token();

        $this->postPet($token);          // succeeds, sets the flag
        unset($_SESSION['celebrate']);   // the creature page would clear it

        $this->postPet($token);          // on cooldown now — should NOT celebrate
        $this->assertArrayNotHasKey('celebrate', $_SESSION);
    }
}
