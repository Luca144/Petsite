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
 * Tests a creature's owner removing entries from its guestbook.
 *
 * @package Felkyo\Tests\Integration
 *
 * THE PERMISSION HERE IS UNUSUAL, so it is worth being explicit: the person allowed
 * to delete is the CREATURE'S OWNER — not the person who wrote the entry. The
 * guestbook belongs to the creature, not to the visitors who signed it. Most of
 * these tests exist to pin that rule down so nobody "corrects" it later.
 */
final class GuestbookDeletionTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private GuestbookRepository $entries;
    private GuestbookService $service;
    private CreatureRepository $creatures;
    private int $ownerId;
    private int $visitorId;
    private int $creatureId;
    private int $otherCreatureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('guestbook_entries', 'rate_limit_hits', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $this->entries = new GuestbookRepository($this->connection);
        $this->creatures = new CreatureRepository($this->connection);

        $this->service = new GuestbookService(
            $this->entries,
            new GuestbookMessages(['lovely-creature' => 'What a lovely creature.']),
            86400
        );

        $controller = new GuestbookController(
            $session,
            $this->csrf,
            $this->creatures,
            $this->service,
            $this->entries,
            new RateLimiter(new RateLimitRepository($this->connection)),
            ['max_attempts' => 50, 'window_seconds' => 3600]
        );

        $this->router = new Router();
        $this->router->post('/creature/{id}/guestbook/{entryId}/delete', [$controller, 'delete']);

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->visitorId = $users->create('visitor', 'visitor@example.com', 'hash')->id;

        $speciesId = $species->findStarters()[0]->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $speciesId, 'Biscuit')->id;
        // A second creature, owned by the VISITOR, used to prove an owner cannot
        // reach into a guestbook that is not theirs.
        $this->otherCreatureId = $this->creatures->create($this->visitorId, $speciesId, 'Marlow')->id;
    }

    /** Have the visitor sign a creature, and return the id of the new entry. */
    private function signAsVisitor(?int $creatureId = null): int
    {
        $creatureId = $creatureId ?? $this->creatureId;
        $this->service->sign($this->visitorId, $this->creatures->findById($creatureId), 'lovely-creature');

        return $this->entries->findByCreatureAndAuthor($creatureId, $this->visitorId)->id;
    }

    private function postDelete(int $creatureId, int $entryId, string $token): Response
    {
        return $this->router->dispatch(new Request(
            'POST',
            '/creature/' . $creatureId . '/guestbook/' . $entryId . '/delete',
            ['_csrf_token' => $token],
            '127.0.0.1'
        ));
    }

    private function countEntriesFor(int $creatureId): int
    {
        return count($this->entries->listForCreature($creatureId, 50));
    }

    public function testTheCreatureOwnerCanRemoveSomeoneElsesEntry(): void
    {
        $entryId = $this->signAsVisitor();
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->postDelete($this->creatureId, $entryId, $this->csrf->token());

        $this->assertSame(302, $response->statusCode());
        $this->assertSame(0, $this->countEntriesFor($this->creatureId));
    }

    /**
     * The author of an entry may NOT delete it — only the creature's owner may.
     * The author can still change their own message once a day instead.
     */
    public function testTheAuthorOfAnEntryCannotDeleteIt(): void
    {
        $entryId = $this->signAsVisitor();
        $_SESSION['user_id'] = $this->visitorId;

        $this->postDelete($this->creatureId, $entryId, $this->csrf->token());

        $this->assertSame(1, $this->countEntriesFor($this->creatureId));
    }

    public function testAGuestCannotDeleteAnything(): void
    {
        $entryId = $this->signAsVisitor();

        $response = $this->postDelete($this->creatureId, $entryId, $this->csrf->token());

        $this->assertSame('/login', $response->header('Location'));
        $this->assertSame(1, $this->countEntriesFor($this->creatureId));
    }

    public function testDeletingWithoutAValidCsrfTokenRemovesNothing(): void
    {
        $entryId = $this->signAsVisitor();
        $_SESSION['user_id'] = $this->ownerId;

        $this->postDelete($this->creatureId, $entryId, 'wrong-token');

        $this->assertSame(1, $this->countEntriesFor($this->creatureId));
    }

    /**
     * The safety case: an owner may only reach entries in their OWN creature's
     * guestbook, even when aiming a real entry id at it. The creature id is part
     * of the DELETE statement, so it simply cannot reach across.
     */
    public function testAnOwnerCannotDeleteAnEntryBelongingToAnotherCreature(): void
    {
        $marlowEntryId = $this->signAsVisitor($this->otherCreatureId);
        $_SESSION['user_id'] = $this->ownerId;

        $this->postDelete($this->creatureId, $marlowEntryId, $this->csrf->token());

        $this->assertSame(
            1,
            $this->countEntriesFor($this->otherCreatureId),
            'An entry in another creature\'s guestbook must be untouchable from here.'
        );
    }

    /**
     * The behaviour the Product Owner asked for: once an entry is removed, that
     * person may sign again. Deleting the row frees the unique slot, so this needs
     * no special handling — but it does need a test, because it is a promise.
     */
    public function testAfterRemovalTheSamePersonCanSignAgain(): void
    {
        $entryId = $this->signAsVisitor();
        $_SESSION['user_id'] = $this->ownerId;
        $this->postDelete($this->creatureId, $entryId, $this->csrf->token());

        // Straight away — the once-a-day change limit does not apply, because there
        // is no longer an entry to change.
        $result = $this->service->sign(
            $this->visitorId,
            $this->creatures->findById($this->creatureId),
            'lovely-creature'
        );

        $this->assertTrue($result->isAccepted());
        $this->assertSame(1, $this->countEntriesFor($this->creatureId));
    }

    public function testRemovingAnEntryThatIsAlreadyGoneIsHarmless(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->postDelete($this->creatureId, 999999, $this->csrf->token());

        $this->assertSame(302, $response->statusCode());
        $this->assertSame(0, $this->countEntriesFor($this->creatureId));
    }
}
