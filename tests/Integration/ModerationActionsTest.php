<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Safety\ReportReason;
use Felkyo\Safety\ReportRepository;
use Felkyo\Safety\ReportSubject;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;

/**
 * Every moderation action, through the real routes — and every guard.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.7 plan's promises: each action changes what it says and leaves
 * its audit row and its notice; dismiss unhides; the queue derives its
 * targets from the report server-side; nobody moderates themselves or
 * staff; durations come from the fixed offer only.
 */
final class ModerationActionsTest extends DatabaseTestCase
{
    private AdminWorld $world;
    private User $moderator;
    private int $playerId;
    private int $creatureId;
    private CreatureRepository $creatures;
    private ReportRepository $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'moderation_notices', 'reports', 'admin_recovery_codes',
            'admin_second_factors', 'admin_audit_log', 'user_roles', 'pettings',
            'creatures', 'users'
        );
        $_SESSION = [];
        $this->world = AdminWorld::build($this->connection);
        $this->moderator = $this->world->makeStaff('mira', Role::Moderator);
        $this->world->logIn($this->moderator);
        $this->world->passDoorFor($this->moderator);

        // The player being moderated: one creature with a bio to point at.
        $users = new UserRepository($this->connection);
        $this->playerId = $users->create('rowan', 'rowan@example.com', 'hash')->id;
        $this->creatures = new CreatureRepository($this->connection);
        $species = (new SpeciesRepository($this->connection))->findStarters()[0];
        $this->creatureId = $this->creatures->create($this->playerId, $species->id, 'Biscuit')->id;
        $this->creatures->updateBio($this->creatureId, $this->playerId, 'a rude bio');
        $this->reports = new ReportRepository($this->connection);
    }

    protected function tearDown(): void
    {
        $this->world->cleanUpArtFolder();
        parent::tearDown();
    }

    private function post(string $path, array $fields = []): Response
    {
        $fields['_csrf_token'] = $this->world->csrf->token();

        return $this->world->router->dispatch(new Request('POST', $path, $fields, '127.0.0.1'));
    }

    private function get(string $path): Response
    {
        return $this->world->router->dispatch(new Request('GET', $path, [], '127.0.0.1'));
    }

    /** File a report about the fixture creature's bio and return its id. */
    private function fileBioReport(): int
    {
        $reporterId = (new UserRepository($this->connection))
            ->create('witness' . substr(uniqid(), -6), uniqid() . '@example.com', 'hash')->id;
        $this->reports->file($reporterId, ReportSubject::CreatureBio, $this->creatureId, $this->playerId, ReportReason::RudeWords);
        $this->reports->hideCreatureBio($this->creatureId);

        return (int) $this->connection->query('SELECT MAX(id) FROM reports')->fetchColumn();
    }

    private function noticeKindsFor(int $userId): array
    {
        return array_map(
            static fn ($notice) => $notice->kind,
            $this->world->notices->allFor($userId)
        );
    }

    public function testTheQueueShowsTheReportedWordsAndTheReporter(): void
    {
        $this->fileBioReport();

        $body = $this->get('/admin/reports')->body();

        $this->assertStringContainsString('a rude bio', $body, 'staff must see the reported text, even hidden text');
        $this->assertStringContainsString('rowan', $body);
        $this->assertStringContainsString('witness', $body);
    }

    public function testDismissClosesTheReportAndBringsHiddenWordsBack(): void
    {
        $reportId = $this->fileBioReport();
        $this->assertNotNull($this->creatures->findById($this->creatureId)->bioHiddenAt, 'the fixture starts hidden');

        $this->post('/admin/reports/' . $reportId . '/dismiss');

        $report = $this->world->moderationRepo->findReport($reportId);
        $this->assertSame('dismissed', $report['status']);
        $creature = $this->creatures->findById($this->creatureId);
        $this->assertNull($creature->bioHiddenAt, 'a dismissed report must not leave words invisible');
        $this->assertSame('a rude bio', $creature->bio, 'dismissing changes nothing but the flag');
    }

    public function testRemovingTheTextClearsItNotifiesTheOwnerAndActionsTheReport(): void
    {
        $reportId = $this->fileBioReport();

        $this->post('/admin/reports/' . $reportId . '/remove-text');

        $creature = $this->creatures->findById($this->creatureId);
        $this->assertNull($creature->bio);
        $this->assertNull($creature->bioHiddenAt, 'nothing left to hide once removed');
        $this->assertSame('actioned', $this->world->moderationRepo->findReport($reportId)['status']);
        $this->assertContains('bio_removed', $this->noticeKindsFor($this->playerId));
        $audited = array_column($this->world->auditLog->recent(10), 'action');
        $this->assertContains('creature.text_removed', $audited);
    }

    public function testRenamingAPlayerForcesTheNeutralNameAndItsSkeleton(): void
    {
        $users = new UserRepository($this->connection);
        $reporterId = $users->create('witness' . substr(uniqid(), -6), uniqid() . '@example.com', 'hash')->id;
        $this->reports->file($reporterId, ReportSubject::Username, $this->playerId, $this->playerId, ReportReason::RudeWords);
        $reportId = (int) $this->connection->query('SELECT MAX(id) FROM reports')->fetchColumn();

        $this->post('/admin/reports/' . $reportId . '/rename');

        $renamed = $users->findById($this->playerId);
        $this->assertSame('Wanderer' . $this->playerId, $renamed->username);
        $this->assertNotNull($users->findByUsernameSkeleton(
            (new \Felkyo\Safety\ImpersonationGuard())->skeletonOf('Wanderer' . $this->playerId)
        ), 'the impersonation skeleton must follow the forced name');
        $this->assertContains('player_renamed', $this->noticeKindsFor($this->playerId));
    }

    public function testMutingUsesOnlyTheOfferedDurationsAndLeavesADatedNotice(): void
    {
        $this->post('/admin/accounts/' . $this->playerId . '/mute', ['days' => '7']);

        $state = $this->world->moderationRepo->stateFor($this->playerId);
        $this->assertTrue($state['is_muted']);
        $notices = $this->world->notices->allFor($this->playerId);
        $this->assertSame('muted', $notices[0]->kind);
        $this->assertNotNull($notices[0]->detail, 'the card must be able to say until when');

        // An invented duration is refused and changes nothing further.
        $this->post('/admin/accounts/' . $this->playerId . '/mute', ['days' => '3650']);
        $this->assertStringContainsString('not one of the offered durations', $_SESSION['flash'] ?? '');
    }

    public function testSuspendingAndLiftingBothLandInTheAuditLog(): void
    {
        $this->post('/admin/accounts/' . $this->playerId . '/suspend', ['days' => '1']);
        $this->assertTrue($this->world->moderationRepo->stateFor($this->playerId)['is_suspended']);

        $this->post('/admin/accounts/' . $this->playerId . '/unsuspend');
        $this->assertFalse($this->world->moderationRepo->stateFor($this->playerId)['is_suspended']);

        $audited = array_column($this->world->auditLog->recent(10), 'action');
        $this->assertContains('player.suspended', $audited);
        $this->assertContains('player.unsuspended', $audited);
    }

    public function testNobodyModeratesThemselvesOrStaff(): void
    {
        // Yourself: refused.
        $this->post('/admin/accounts/' . $this->moderator->id . '/warn');
        $this->assertStringContainsString('your own account', $_SESSION['flash'] ?? '');

        // Another staff member: refused — that is the owner's business.
        $otherStaff = $this->world->makeStaff('sable', Role::Artist);
        $this->post('/admin/accounts/' . $otherStaff->id . '/warn');
        $this->assertStringContainsString('staff', $_SESSION['flash'] ?? '');
        $this->assertSame([], $this->noticeKindsFor($otherStaff->id), 'no notice may be created by a refused action');
    }

    public function testTheAccountHistoryPageShowsThePattern(): void
    {
        $this->fileBioReport();
        $this->post('/admin/accounts/' . $this->playerId . '/warn');

        $body = $this->get('/admin/accounts/' . $this->playerId)->body();

        $this->assertStringContainsString('rowan', $body);
        $this->assertStringContainsString('Biscuit', $body);
        $this->assertStringContainsString('a rude bio', $body);
        $this->assertStringContainsString('warned', $body);
        $this->assertStringContainsString('sent a player a warning notice', $body);
    }
}
