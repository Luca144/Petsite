<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Guestbook\GuestbookMessages;
use Felkyo\Guestbook\GuestbookRepository;
use Felkyo\Guestbook\GuestbookService;
use Felkyo\Http\Controllers\GuestbookController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests signing a guestbook through the router — the permission rules especially.
 *
 * @package Felkyo\Tests\Integration
 *
 * The service tests cover the guestbook's own rules. These cover the gate in front
 * of them: you must be logged in, you must send a valid CSRF token, and you cannot
 * sign the guestbook of a creature you are not allowed to see.
 */
final class GuestbookControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private GuestbookRepository $entries;
    private int $ownerId;
    private int $visitorId;
    private int $creatureId;
    private int $privateCreatureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('guestbook_entries', 'rate_limit_hits', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $this->entries = new GuestbookRepository($this->connection);
        $creatures = new CreatureRepository($this->connection);

        $service = new GuestbookService(
            $this->entries,
            new GuestbookMessages(['lovely-creature' => 'What a lovely creature.']),
            86400
        );

        $controller = new GuestbookController(
            $session,
            $this->csrf,
            $creatures,
            $service,
            $this->entries,
            new RateLimiter(new RateLimitRepository($this->connection)),
            ['max_attempts' => 20, 'window_seconds' => 3600]
        );

        $this->router = new Router();
        $this->router->post('/creature/{id}/guestbook', [$controller, 'sign']);

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->visitorId = $users->create('visitor', 'visitor@example.com', 'hash')->id;

        $starterSpeciesId = $species->findStarters()[0]->id;
        $this->creatureId = $creatures->create($this->ownerId, $starterSpeciesId, 'Biscuit')->id;

        // A second creature, hidden from everyone but its owner.
        $this->privateCreatureId = $creatures->create($this->ownerId, $starterSpeciesId, 'Shy')->id;
        $this->connection
            ->prepare('UPDATE creatures SET is_public = 0 WHERE id = :id')
            ->execute([':id' => $this->privateCreatureId]);
    }

    private function postSigning(int $creatureId, string $token, string $messageKey): Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/creature/' . $creatureId . '/guestbook',
            ['_csrf_token' => $token, 'message_key' => $messageKey],
            '127.0.0.1'
        ));
    }

    private function countEntriesFor(int $creatureId): int
    {
        return count($this->entries->listForCreature($creatureId, 50));
    }

    public function testALoggedInVisitorCanSignAGuestbook(): void
    {
        $_SESSION['user_id'] = $this->visitorId;

        $response = $this->postSigning($this->creatureId, $this->csrf->token(), 'lovely-creature');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/creature/' . $this->creatureId, $response->header('Location'));
        $this->assertSame(1, $this->countEntriesFor($this->creatureId));
    }

    public function testSigningRequiresLogin(): void
    {
        $response = $this->postSigning($this->creatureId, $this->csrf->token(), 'lovely-creature');

        $this->assertSame('/login', $response->header('Location'));
        $this->assertSame(0, $this->countEntriesFor($this->creatureId));
    }

    public function testSigningWithoutAValidCsrfTokenWritesNothing(): void
    {
        $_SESSION['user_id'] = $this->visitorId;

        $this->postSigning($this->creatureId, 'wrong-token', 'lovely-creature');

        $this->assertSame(0, $this->countEntriesFor($this->creatureId));
    }

    /**
     * A private creature is invisible to strangers, so its guestbook is closed to
     * them as well — otherwise signing would quietly confirm the creature exists.
     */
    public function testAStrangerCannotSignAPrivateCreaturesGuestbook(): void
    {
        $_SESSION['user_id'] = $this->visitorId;

        $response = $this->postSigning($this->privateCreatureId, $this->csrf->token(), 'lovely-creature');

        $this->assertSame('/', $response->header('Location'));
        $this->assertSame(0, $this->countEntriesFor($this->privateCreatureId));
    }

    public function testSigningACreatureThatDoesNotExistWritesNothing(): void
    {
        $_SESSION['user_id'] = $this->visitorId;

        $response = $this->postSigning(999999, $this->csrf->token(), 'lovely-creature');

        $this->assertSame('/', $response->header('Location'));
    }

    /**
     * The rule the Product Owner asked for, checked end to end: however many times
     * the form is submitted, one person leaves exactly one entry per creature.
     */
    public function testSubmittingTheFormRepeatedlyStillLeavesOneEntry(): void
    {
        $_SESSION['user_id'] = $this->visitorId;

        $this->postSigning($this->creatureId, $this->csrf->token(), 'lovely-creature');
        $this->postSigning($this->creatureId, $this->csrf->token(), 'lovely-creature');
        $this->postSigning($this->creatureId, $this->csrf->token(), 'lovely-creature');

        $this->assertSame(1, $this->countEntriesFor($this->creatureId));
    }

    /**
     * A message key that was never offered must not be stored, even though it
     * arrives through a perfectly valid, logged-in, CSRF-protected request.
     */
    public function testAMadeUpMessageKeyIsNotStored(): void
    {
        $_SESSION['user_id'] = $this->visitorId;

        $this->postSigning($this->creatureId, $this->csrf->token(), 'visit-my-spam-site');

        $this->assertSame(0, $this->countEntriesFor($this->creatureId));
    }
}
