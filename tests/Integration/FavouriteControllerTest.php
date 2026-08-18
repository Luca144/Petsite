<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\FavouriteController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\ProfileService;
use Felkyo\Users\UserRepository;

/**
 * Tests the favourite star — and mostly tests who cannot press it.
 *
 * @package Felkyo\Tests\Integration
 *
 * Starring is a small feature with a real permission boundary behind it: it
 * changes which creatures appear on somebody's public page. So the tests here are
 * mostly refusals — a stranger, a missing token, and the cap — plus the one thing
 * a toggle can get wrong, which is going only one way.
 */
final class FavouriteControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private CreatureRepository $creatures;
    private int $ownerId;
    private int $strangerId;
    private int $creatureId;
    private int $speciesId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $this->creatures = new CreatureRepository($this->connection);

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $this->speciesId = $species->findStarters()[0]->id;

        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->strangerId = $users->create('stranger', 'stranger@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $this->speciesId, 'Biscuit')->id;

        $controller = new FavouriteController(
            $session,
            $this->csrf,
            $this->creatures,
            new ProfileService(
                new ProfileRepository($this->connection),
                $this->creatures,
                new AvatarSet(['default' => ['name' => 'The wandering visitor', 'file' => 'default.png']]),
                Guards::textGuard(),
                // A deliberately tiny cap, so a test can reach it in a few lines.
                ['max_about_length' => 500, 'max_featured_creatures' => 2]
            ),
            new RateLimiter(new RateLimitRepository($this->connection)),
            ['max_attempts' => 60, 'window_seconds' => 3600]
        );

        $this->router = new Router();
        $this->router->post('/creature/{id}/favourite', [$controller, 'toggle']);
    }

    private function press(int $creatureId, ?string $token = null, string $from = 'creature'): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/creature/' . $creatureId . '/favourite',
            ['_csrf_token' => $token ?? $this->csrf->token(), 'from' => $from],
            '127.0.0.1'
        ));
    }

    private function isFavourite(int $creatureId): bool
    {
        return $this->creatures->findById($creatureId)->isFeatured();
    }

    // ---- The toggle ----

    public function testTheOwnerCanMakeACreatureAFavourite(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $this->press($this->creatureId);

        $this->assertTrue($this->isFavourite($this->creatureId));
    }

    public function testPressingAgainTakesItBackOff(): void
    {
        // The thing a toggle can get wrong is going only one way. A star that
        // cannot be un-starred is a decision somebody cannot undo, which golden
        // rule 9 has an opinion about.
        $_SESSION['user_id'] = $this->ownerId;

        $this->press($this->creatureId);
        $this->assertTrue($this->isFavourite($this->creatureId));

        $this->press($this->creatureId);
        $this->assertFalse($this->isFavourite($this->creatureId));
    }

    public function testTheOwnerIsToldWhatHappened(): void
    {
        // Golden rule 4: every action gets a plain-language answer, naming the
        // creature it was about.
        $_SESSION['user_id'] = $this->ownerId;

        $this->press($this->creatureId);

        $this->assertStringContainsString('Biscuit', $_SESSION['flash'] ?? '');
    }

    public function testStarringOneCreatureDoesNotUnstarAnother(): void
    {
        // The toggle rewrites the whole featured LIST, so this is the mistake it
        // could plausibly make: replacing the list instead of adding to it.
        $_SESSION['user_id'] = $this->ownerId;
        $second = $this->creatures->create($this->ownerId, $this->speciesId, 'Pip')->id;

        $this->press($this->creatureId);
        $this->press($second);

        $this->assertTrue($this->isFavourite($this->creatureId));
        $this->assertTrue($this->isFavourite($second));
    }

    // ---- What it refuses ----

    public function testAStrangerCannotStarSomebodyElsesCreature(): void
    {
        $_SESSION['user_id'] = $this->strangerId;

        $this->press($this->creatureId);

        $this->assertFalse($this->isFavourite($this->creatureId));
    }

    public function testAGuestIsSentToLogIn(): void
    {
        $response = $this->press($this->creatureId);

        $this->assertSame(302, $response->statusCode());
        $this->assertFalse($this->isFavourite($this->creatureId));
    }

    public function testAMissingTokenChangesNothing(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $this->press($this->creatureId, 'not-the-real-token');

        $this->assertFalse($this->isFavourite($this->creatureId));
    }

    public function testTheCapRefusesOneTooMany(): void
    {
        // The cap in this test is two. The third must be refused, and — this is
        // the part worth checking — the two already chosen must survive intact.
        $_SESSION['user_id'] = $this->ownerId;
        $second = $this->creatures->create($this->ownerId, $this->speciesId, 'Pip')->id;
        $third = $this->creatures->create($this->ownerId, $this->speciesId, 'Clover')->id;

        $this->press($this->creatureId);
        $this->press($second);
        $this->press($third);

        $this->assertFalse($this->isFavourite($third));
        $this->assertTrue($this->isFavourite($this->creatureId));
        $this->assertTrue($this->isFavourite($second));
        $this->assertStringContainsString('up to 2', $_SESSION['flash'] ?? '');
    }

    // ---- Where it sends you ----

    public function testItSendsYouBackToWhereYouPressedIt(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $fromCollection = $this->press($this->creatureId, from: 'collection');
        $this->assertSame('/creatures', $fromCollection->header('Location'));

        $fromCreature = $this->press($this->creatureId, from: 'creature');
        $this->assertSame('/creature/' . $this->creatureId, $fromCreature->header('Location'));
    }

    public function testItNeverRedirectsSomewhereTheBrowserAsked(): void
    {
        // An open redirect is somebody being walked from a real page to a fake
        // login form. "from" chooses between two addresses WE own; it is never
        // used as an address itself.
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->press($this->creatureId, from: 'https://example.com/steal');

        $this->assertSame('/creature/' . $this->creatureId, $response->header('Location'));
    }
}
