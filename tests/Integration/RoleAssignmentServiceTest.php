<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Http\Request;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;

/**
 * The rules of handing out and taking back roles — including every refusal.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.1 plan promises the bad cases are refused, not just that the good
 * ones work: a non-owner (even with a perfect request), an unknown target,
 * the last owner, an owner revoking themself — and that every change that
 * DOES happen leaves an audit entry with before and after.
 */
final class RoleAssignmentServiceTest extends DatabaseTestCase
{
    private AdminWorld $world;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'admin_recovery_codes', 'admin_second_factors',
            'admin_audit_log', 'user_roles', 'pettings', 'creatures', 'users'
        );
        $_SESSION = [];
        $this->world = AdminWorld::build($this->connection);
    }

    // --- Who may do this at all ---

    public function testANonOwnerIsRefusedEvenWithAPerfectRequest(): void
    {
        // A moderator is staff — but staff is not owner, and the service
        // checks the DATABASE, not who managed to call it.
        $moderator = $this->world->makeStaff('mira', Role::Moderator);
        $target = $this->world->makeUser('biscuit');

        $result = $this->world->assignments->grant($moderator, 'biscuit', Role::Artist, '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertSame([], $this->world->roles->rolesFor($target->id));
    }

    public function testAPlainPlayerIsRefused(): void
    {
        $player = $this->world->makeUser('biscuit');
        $target = $this->world->makeUser('crumb');

        $result = $this->world->assignments->grant($player, 'crumb', Role::Owner, '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertSame([], $this->world->roles->rolesFor($target->id));
    }

    // --- Granting ---

    public function testAnOwnerCanGrantARoleAndItIsAudited(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $target = $this->world->makeUser('oki');

        $result = $this->world->assignments->grant($owner, 'oki', Role::Artist, '127.0.0.1');

        $this->assertTrue($result->success);
        $this->assertContains(Role::Artist, $this->world->roles->rolesFor($target->id));

        // The audit entry: who did it, to whom, and the role list before/after.
        $entry = $this->world->auditLog->recent(1)[0];
        $this->assertSame('role.granted', $entry['action']);
        $this->assertSame($owner->id, (int) $entry['actor_user_id']);
        $this->assertSame($target->id, (int) $entry['subject_id']);
        $this->assertStringNotContainsString('artist', (string) $entry['detail_before']);
        $this->assertStringContainsString('artist', (string) $entry['detail_after']);
    }

    public function testGrantingARoleSomebodyAlreadyHoldsChangesNothingAndAuditsNothing(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->makeStaff('oki', Role::Artist);
        $entriesBefore = count($this->world->auditLog->recent(50));

        $result = $this->world->assignments->grant($owner, 'oki', Role::Artist, '127.0.0.1');

        // A friendly no-op: the message says nothing changed, and the audit
        // log records no phantom change.
        $this->assertTrue($result->success);
        $this->assertStringContainsString('nothing changed', $result->message);
        $this->assertCount($entriesBefore, $this->world->auditLog->recent(50));
    }

    public function testGrantingToAnUnknownNameIsRefused(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);

        $result = $this->world->assignments->grant($owner, 'nobody-here', Role::Artist, '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('no account', $result->message);
    }

    // --- Revoking, and the two guards that keep the site ownable ---

    public function testAnOwnerCanRevokeARoleAndItIsAudited(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $artist = $this->world->makeStaff('oki', Role::Artist);

        $result = $this->world->assignments->revoke($owner, 'oki', Role::Artist, '127.0.0.1');

        $this->assertTrue($result->success);
        $this->assertSame([], $this->world->roles->rolesFor($artist->id));
        $this->assertSame('role.revoked', $this->world->auditLog->recent(1)[0]['action']);
    }

    public function testTheSiteCanNeverSequenceItsWayToZeroOwners(): void
    {
        // WHY THIS TEST LOOKS INDIRECT: three guards together make "no owners
        // left" impossible, and each closes a different route there.
        //  - You cannot revoke your OWN owner role (tested below), so the
        //    sole owner cannot remove themself.
        //  - Only an owner can revoke at all, so once you lose the role you
        //    also lose the ability to remove anyone else's.
        //  - Two revocations RACING each other are serialised by a row lock
        //    inside the service's transaction (countHoldersLocked), so the
        //    second racer sees the count the first left behind. A
        //    single-threaded test cannot interleave transactions, so that
        //    guard's arithmetic is pinned separately at the end.
        $skerro = $this->world->makeStaff('skerro', Role::Owner);
        $oki = $this->world->makeStaff('oki', Role::Owner);

        // With two owners, removing one is an ordinary, allowed change.
        $first = $this->world->assignments->revoke($skerro, 'oki', Role::Owner, '127.0.0.1');
        $this->assertTrue($first->success, 'with two owners, revoking one is fine');

        // Now every remaining route to zero is shut: the survivor cannot
        // remove themself...
        $self = $this->world->assignments->revoke($skerro, 'skerro', Role::Owner, '127.0.0.1');
        $this->assertFalse($self->success);

        // ...and the demoted owner can no longer remove anyone.
        $demoted = $this->world->assignments->revoke($oki, 'skerro', Role::Owner, '127.0.0.1');
        $this->assertFalse($demoted->success);

        $this->assertContains(Role::Owner, $this->world->roles->rolesFor($skerro->id),
            'an owner must remain at the end of every sequence');

        // The race guard's arithmetic: the locked count the transaction
        // consults reports the true number of owners.
        $this->connection->beginTransaction();
        $this->assertSame(1, $this->world->roles->countHoldersLocked(Role::Owner));
        $this->connection->rollBack();
    }

    public function testAnOwnerCannotRevokeTheirOwnOwnerRole(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->makeStaff('oki', Role::Owner); // plenty of owners — the guard is about SELF

        $result = $this->world->assignments->revoke($owner, 'skerro', Role::Owner, '127.0.0.1');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('yourself', $result->message);
        $this->assertContains(Role::Owner, $this->world->roles->rolesFor($owner->id));
    }

    public function testAnOwnerMayRevokeTheirOwnLesserRoles(): void
    {
        // The self-guard protects the OWNER role only: dropping your own
        // artist hat is an ordinary tidy-up.
        $owner = $this->world->makeStaff('skerro', Role::Owner, Role::Artist);

        $result = $this->world->assignments->revoke($owner, 'skerro', Role::Artist, '127.0.0.1');

        $this->assertTrue($result->success);
        $this->assertSame([Role::Owner], $this->world->roles->rolesFor($owner->id));
    }

    // --- The web layer's own refusals ---

    public function testTheRolesEndpointRefusesARoleNameOutsideTheAllowList(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->makeUser('oki');
        $this->world->logIn($owner);
        $this->world->passDoorFor($owner);

        $response = $this->world->router->dispatch(new Request('POST', '/admin/roles/grant', [
            '_csrf_token' => $this->world->csrf->token(),
            'username' => 'oki',
            'role' => 'superuser',
        ], '127.0.0.1'));

        // Refused politely (flash + redirect), and no role of any name exists.
        $this->assertSame(302, $response->statusCode());
        $rows = $this->connection->query('SELECT COUNT(*) FROM user_roles WHERE role = \'superuser\'')->fetchColumn();
        $this->assertSame(0, (int) $rows);
    }
}
