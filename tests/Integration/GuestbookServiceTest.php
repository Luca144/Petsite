<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\Creature;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Guestbook\GuestbookMessages;
use Felkyo\Guestbook\GuestbookRepository;
use Felkyo\Guestbook\GuestbookService;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests the guestbook rules against the real test database.
 *
 * @package Felkyo\Tests\Integration
 *
 * These cover the three rules the guestbook promises: only offered messages may be
 * chosen, one entry per person per creature, and one change per day.
 */
final class GuestbookServiceTest extends DatabaseTestCase
{
    private GuestbookService $service;
    private GuestbookRepository $entries;
    private Creature $creature;
    private int $visitorId;
    private int $otherVisitorId;

    /** One day, matching the real config default. */
    private const ONE_DAY = 86400;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('guestbook_entries', 'pettings', 'creatures', 'users');

        $this->entries = new GuestbookRepository($this->connection);
        $this->service = new GuestbookService(
            $this->entries,
            new GuestbookMessages([
                'lovely-creature' => 'What a lovely creature.',
                'warm-wishes' => 'Warm wishes from a fellow wanderer.',
            ]),
            self::ONE_DAY
        );

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $creatures = new CreatureRepository($this->connection);

        $ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->visitorId = $users->create('visitor', 'visitor@example.com', 'hash')->id;
        $this->otherVisitorId = $users->create('other', 'other@example.com', 'hash')->id;
        $this->creature = $creatures->create($ownerId, $species->findStarters()[0]->id, 'Biscuit');
    }

    /**
     * Pretend an entry was last changed some seconds ago, so the once-a-day rule
     * can be tested without waiting a real day.
     */
    private function pretendEntryWasChangedSecondsAgo(int $entryId, int $secondsAgo): void
    {
        // MySQL will not accept a bound parameter inside INTERVAL, so the number of
        // seconds goes straight into the statement. That is safe here because it is
        // a value this test chooses itself — never user input.
        $statement = $this->connection->prepare(
            'UPDATE guestbook_entries SET updated_at = NOW() - INTERVAL ' . $secondsAgo . ' SECOND
              WHERE id = :id'
        );
        $statement->execute([':id' => $entryId]);
    }

    private function countEntries(): int
    {
        return (int) $this->connection
            ->query('SELECT COUNT(*) FROM guestbook_entries')
            ->fetchColumn();
    }

    public function testAVisitorCanSignAGuestbook(): void
    {
        $result = $this->service->sign($this->visitorId, $this->creature, 'lovely-creature');

        $this->assertTrue($result->isAccepted());
        $this->assertSame(1, $this->countEntries());

        $entry = $this->entries->findByCreatureAndAuthor($this->creature->id, $this->visitorId);
        $this->assertSame('lovely-creature', $entry->messageKey);
        $this->assertSame('visitor', $entry->authorUsername);
    }

    public function testAMessageThatIsNotOnTheListIsRejected(): void
    {
        $result = $this->service->sign($this->visitorId, $this->creature, 'buy-cheap-pills-now');

        $this->assertFalse($result->isAccepted());
        $this->assertSame(0, $this->countEntries());
    }

    /**
     * The headline rule: signing again does not add a second entry. Here the day
     * has passed, so the existing entry is changed instead.
     */
    public function testSigningTwiceChangesTheEntryInsteadOfAddingASecondOne(): void
    {
        $this->service->sign($this->visitorId, $this->creature, 'lovely-creature');
        $entryId = $this->entries->findByCreatureAndAuthor($this->creature->id, $this->visitorId)->id;
        $this->pretendEntryWasChangedSecondsAgo($entryId, self::ONE_DAY + 60);

        $result = $this->service->sign($this->visitorId, $this->creature, 'warm-wishes');

        $this->assertTrue($result->isAccepted());
        $this->assertSame(1, $this->countEntries(), 'Signing twice must never create a second entry.');
        $this->assertSame(
            'warm-wishes',
            $this->entries->findByCreatureAndAuthor($this->creature->id, $this->visitorId)->messageKey
        );
    }

    public function testTheEntryCannotBeChangedAgainOnTheSameDay(): void
    {
        $this->service->sign($this->visitorId, $this->creature, 'lovely-creature');

        $result = $this->service->sign($this->visitorId, $this->creature, 'warm-wishes');

        $this->assertFalse($result->isAccepted());
        $this->assertSame(
            'lovely-creature',
            $this->entries->findByCreatureAndAuthor($this->creature->id, $this->visitorId)->messageKey,
            'The message must be unchanged when the daily limit refuses the change.'
        );
    }

    /**
     * The exact-boundary case: one second SHORT of a full day is still refused.
     */
    public function testAChangeOneSecondBeforeTheDayIsUpIsStillRefused(): void
    {
        $this->service->sign($this->visitorId, $this->creature, 'lovely-creature');
        $entryId = $this->entries->findByCreatureAndAuthor($this->creature->id, $this->visitorId)->id;
        $this->pretendEntryWasChangedSecondsAgo($entryId, self::ONE_DAY - 1);

        $result = $this->service->sign($this->visitorId, $this->creature, 'warm-wishes');

        $this->assertFalse($result->isAccepted());
    }

    /**
     * Choosing the message that is already stored must not quietly use up the one
     * change the visitor gets that day.
     */
    public function testChoosingTheSameMessageAgainDoesNotUseUpTheDailyChange(): void
    {
        $this->service->sign($this->visitorId, $this->creature, 'lovely-creature');
        $entryId = $this->entries->findByCreatureAndAuthor($this->creature->id, $this->visitorId)->id;
        $this->pretendEntryWasChangedSecondsAgo($entryId, self::ONE_DAY + 60);

        // Re-picking the same message: accepted, but nothing is written.
        $sameAgain = $this->service->sign($this->visitorId, $this->creature, 'lovely-creature');
        $this->assertTrue($sameAgain->isAccepted());

        // The real change is still available immediately afterwards.
        $realChange = $this->service->sign($this->visitorId, $this->creature, 'warm-wishes');
        $this->assertTrue($realChange->isAccepted());
        $this->assertSame(
            'warm-wishes',
            $this->entries->findByCreatureAndAuthor($this->creature->id, $this->visitorId)->messageKey
        );
    }

    /**
     * "One entry per person" is per person — two different visitors each get one.
     */
    public function testDifferentVisitorsEachGetTheirOwnEntry(): void
    {
        $this->service->sign($this->visitorId, $this->creature, 'lovely-creature');
        $this->service->sign($this->otherVisitorId, $this->creature, 'warm-wishes');

        $this->assertSame(2, $this->countEntries());
        $this->assertCount(2, $this->entries->listForCreature($this->creature->id, 20));
    }

    public function testAGuestbookWithNoSignaturesIsSimplyEmpty(): void
    {
        $this->assertSame([], $this->entries->listForCreature($this->creature->id, 20));
    }
}
