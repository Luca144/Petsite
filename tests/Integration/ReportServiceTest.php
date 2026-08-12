<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Safety\ReportReason;
use Felkyo\Safety\ReportRepository;
use Felkyo\Safety\ReportService;
use Felkyo\Safety\ReportSubject;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\UserRepository;

/**
 * Tests reporting — the mechanism every other safety measure exists to support.
 *
 * @package Felkyo\Tests\Integration
 *
 * The filters on this site catch the obvious. A person noticing and saying so
 * catches the rest, so if this quietly stops working, nothing else notices.
 *
 * Scenario: Mira has a creature and an about text. Rowan reports things. Wren is
 * a third player, so "two different people reported this" can be told apart from
 * "one person reported it twice".
 */
final class ReportServiceTest extends DatabaseTestCase
{
    private ReportService $service;
    private ReportRepository $reports;
    private ProfileRepository $profiles;
    private int $miraId;
    private int $rowanId;
    private int $wrenId;
    private int $mirasCreatureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('reports', 'inventory', 'pettings', 'creatures', 'users');

        $users = new UserRepository($this->connection);
        $creatures = new CreatureRepository($this->connection);
        $this->profiles = new ProfileRepository($this->connection);
        $this->reports = new ReportRepository($this->connection);
        $this->service = new ReportService($this->reports, $creatures, $this->profiles);

        $this->miraId = $users->create('mira', 'mira@example.com', 'hash')->id;
        $this->rowanId = $users->create('rowan', 'rowan@example.com', 'hash')->id;
        $this->wrenId = $users->create('wren', 'wren@example.com', 'hash')->id;

        $speciesId = (new SpeciesRepository($this->connection))->findStarters()[0]->id;
        $this->mirasCreatureId = $creatures->create($this->miraId, $speciesId, 'Biscuit')->id;

        $this->connection->exec(
            "UPDATE creatures SET bio = 'A gentle creature.' WHERE id = " . $this->mirasCreatureId
        );
        $this->connection->exec("UPDATE users SET about = 'Hello!' WHERE id = " . $this->miraId);
    }

    private function reportRows(): array
    {
        return $this->connection->query('SELECT * FROM reports')->fetchAll();
    }

    // ---- Filing a report ----

    public function testAReportIsStoredWithItsReasonAndPriority(): void
    {
        $result = $this->service->report(
            $this->rowanId,
            ReportSubject::CreatureBio,
            $this->mirasCreatureId,
            ReportReason::AdultContact
        );

        $this->assertTrue($result->wasReceived());

        $rows = $this->reportRows();
        $this->assertCount(1, $rows);
        $this->assertSame('adult_contact', $rows[0]['category']);
        // The priority is copied in at filing time, so retuning a category later
        // cannot silently reorder reports that are already waiting.
        $this->assertSame(1, (int) $rows[0]['priority']);
        // And it is pinned to the account it is about, because judging reports one
        // at a time is how a repeat offender stays under the line.
        $this->assertSame($this->miraId, (int) $rows[0]['about_user_id']);
    }

    public function testTheReporterIsThankedAndToldWhatHappens(): void
    {
        // Silence teaches people not to report, and reporting is the main safety
        // mechanism here. The answer promises only what is true.
        $result = $this->service->report(
            $this->rowanId,
            ReportSubject::CreatureBio,
            $this->mirasCreatureId,
            ReportReason::RudeWords
        );

        $this->assertStringContainsString('Thank you', $result->message());
        $this->assertStringContainsString('read it', $result->message());
    }

    public function testTheSamePersonReportingTwiceIsStoredOnce(): void
    {
        // Without this, one person could bury a queue that two part-time humans
        // have to read. It also makes "how many DIFFERENT people" meaningful.
        $this->service->report($this->rowanId, ReportSubject::CreatureBio, $this->mirasCreatureId, ReportReason::RudeWords);
        $second = $this->service->report($this->rowanId, ReportSubject::CreatureBio, $this->mirasCreatureId, ReportReason::Bullying);

        $this->assertCount(1, $this->reportRows());
        // They are thanked identically either way — telling them apart would serve
        // nobody and would confirm what is already stored.
        $this->assertTrue($second->wasReceived());
        $this->assertStringContainsString('Thank you', $second->message());
    }

    public function testTwoDifferentPeopleReportingBothCount(): void
    {
        $this->service->report($this->rowanId, ReportSubject::CreatureBio, $this->mirasCreatureId, ReportReason::RudeWords);
        $this->service->report($this->wrenId, ReportSubject::CreatureBio, $this->mirasCreatureId, ReportReason::RudeWords);

        $this->assertSame(2, $this->reports->countReportsFor(ReportSubject::CreatureBio, $this->mirasCreatureId));
    }

    // ---- Hiding pending review ----

    public function testAReportedBioIsHiddenUntilSomebodyLooks(): void
    {
        $this->service->report($this->rowanId, ReportSubject::CreatureBio, $this->mirasCreatureId, ReportReason::SexualContent);

        $hiddenAt = $this->connection
            ->query('SELECT bio_hidden_at FROM creatures WHERE id = ' . $this->mirasCreatureId)
            ->fetchColumn();

        $this->assertNotNull($hiddenAt);
        $this->assertNotFalse($hiddenAt);
    }

    public function testAReportedAboutTextIsHiddenUntilSomebodyLooks(): void
    {
        $this->service->report($this->rowanId, ReportSubject::ProfileAbout, $this->miraId, ReportReason::ContactDetails);

        $this->assertTrue($this->profiles->findById($this->miraId)->isAboutHidden());
    }

    public function testASecondReportDoesNotRestartTheClock(): void
    {
        // The queue shows how long something has been waiting, and that has to be
        // measured from the FIRST report — otherwise a steady trickle of reports
        // would make an old item look new and it would never reach the top.
        $this->service->report($this->rowanId, ReportSubject::ProfileAbout, $this->miraId, ReportReason::RudeWords);
        $firstHiddenAt = $this->profiles->findById($this->miraId)->aboutHiddenAt;

        $this->service->report($this->wrenId, ReportSubject::ProfileAbout, $this->miraId, ReportReason::Bullying);

        $this->assertSame($firstHiddenAt, $this->profiles->findById($this->miraId)->aboutHiddenAt);
    }

    public function testAReportedNameIsFlaggedButNotHidden(): void
    {
        // Hiding a name would break every page it appears on, and would let
        // anybody erase another player from the site by reporting them.
        $this->assertFalse(ReportSubject::Username->hidesUntilReviewed());
        $this->assertFalse(ReportSubject::CreatureName->hidesUntilReviewed());

        $this->service->report($this->rowanId, ReportSubject::Username, $this->miraId, ReportReason::RudeWords);

        $this->assertCount(1, $this->reportRows());
        $this->assertFalse($this->profiles->findById($this->miraId)->isAboutHidden());
    }

    // ---- Refusals ----

    public function testReportingSomethingThatIsNotThereIsRefused(): void
    {
        $result = $this->service->report($this->rowanId, ReportSubject::CreatureBio, 999999, ReportReason::RudeWords);

        $this->assertFalse($result->wasReceived());
        $this->assertSame([], $this->reportRows());
    }

    public function testReportingYourOwnThingIsRefusedKindly(): void
    {
        // Almost always a misunderstanding of what the button does. The refusal
        // points at the thing they can actually do instead.
        $result = $this->service->report($this->miraId, ReportSubject::CreatureBio, $this->mirasCreatureId, ReportReason::RudeWords);

        $this->assertFalse($result->wasReceived());
        $this->assertStringContainsString('change it yourself', $result->message());
        $this->assertSame([], $this->reportRows());
    }

    public function testAnInventedReasonOrSubjectIsNotOneOfOurs(): void
    {
        // Both are enums, so a value we never offered simply is not one — the
        // controller turns these nulls into a quiet redirect.
        $this->assertNull(ReportReason::fromFormValue('because_i_said_so'));
        $this->assertNull(ReportSubject::fromFormValue('the_whole_website'));
    }

    // ---- The reasons themselves ----

    public function testTheMostSeriousReasonsAreOfferedFirstAndRankHighest(): void
    {
        // Somebody frightened should not have to read past "there's a rude word"
        // to find the one that describes what is happening to them.
        $offered = ReportReason::inOfferedOrder();

        $this->assertSame(ReportReason::AdultContact, $offered[0]);
        $this->assertSame(1, $offered[0]->priority());
        $this->assertTrue($offered[0]->needsSomebodyNow());

        $priorities = array_map(static fn (ReportReason $r): int => $r->priority(), $offered);
        $sorted = $priorities;
        sort($sorted);
        $this->assertSame($sorted, $priorities, 'Reasons should be offered most serious first.');
    }

    public function testEveryReasonHasWordsAChildWouldRecognise(): void
    {
        foreach (ReportReason::cases() as $reason) {
            $this->assertNotSame('', $reason->label());
            // No jargon: a label should read as a description of what happened.
            $this->assertStringNotContainsStringIgnoringCase('violation', $reason->label());
            $this->assertStringNotContainsStringIgnoringCase('policy', $reason->label());
        }
    }
}
