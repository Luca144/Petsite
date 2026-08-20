<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use PDO;

/**
 * The rules for handing out and taking away staff roles.
 *
 * @package Felkyo\Admin
 *
 * WHO MAY DO THIS: only an owner — and this class re-checks that against the
 * database itself, even though the AdminGate already did. Belt and braces on
 * purpose: role assignment is the single most dangerous write on the site
 * (whoever controls it controls everything), so its permission check must not
 * depend on every caller having been wired through the gate correctly.
 *
 * THE TWO GUARDS THAT KEEP THE SITE OWNABLE:
 *  - The LAST owner can never be revoked. Otherwise the site could end up
 *    with nobody able to assign roles — recoverable only by shell access.
 *    The count is taken under a row lock inside the same transaction as the
 *    delete, so two revocations racing each other cannot both slip past it.
 *  - An owner cannot revoke their OWN owner role. The simple companion to
 *    the last-owner rule: you hand the keys to someone else first, and then
 *    THEY remove yours. Removing yourself in a moment of tidying up, with
 *    the other owner on holiday, is exactly the accident this refuses.
 *
 * Every change that actually happens is written to the audit log with the
 * target's role list before and after, in the same transaction — a role
 * change that failed to be recorded does not happen at all.
 */
final class RoleAssignmentService
{
    public function __construct(
        private PDO $connection,
        private RoleRepository $roles,
        private UserRepository $users,
        private AuditLogRepository $auditLog,
    ) {
    }

    /**
     * Grant a role to the named user. $actor is the logged-in user asking.
     */
    public function grant(User $actor, string $targetUsername, Role $role, string $ip): RoleChangeResult
    {
        $refusal = $this->refuseUnlessOwner($actor);
        if ($refusal !== null) {
            return $refusal;
        }

        $target = $this->users->findByUsername(trim($targetUsername));
        if ($target === null) {
            return RoleChangeResult::refused('There is no account with that name. Check the spelling — it has to match exactly.');
        }

        $this->connection->beginTransaction();
        try {
            $rolesBefore = $this->roles->rolesFor($target->id);
            $granted = $this->roles->grant($target->id, $role, $actor->id);

            if ($granted) {
                $this->auditLog->record(new AuditEntry(
                    actorUserId: $actor->id,
                    action: AuditAction::RoleGranted,
                    subjectType: 'user',
                    subjectId: $target->id,
                    before: ['roles' => $this->roleNames($rolesBefore)],
                    after: ['roles' => $this->roleNames($this->roles->rolesFor($target->id))],
                    ip: $ip,
                ));
            }

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        if (!$granted) {
            return RoleChangeResult::ok(sprintf('%s already had the %s role — nothing changed.', $target->username, $role->label()));
        }

        // "now has the Artist role", not "is now a(n) Artist" — phrased so no
        // role name ever needs the a/an decision made for it.
        return RoleChangeResult::ok(sprintf('%s now has the %s role. %s', $target->username, $role->label(), $role->description()));
    }

    /**
     * Take a role away from the named user.
     */
    public function revoke(User $actor, string $targetUsername, Role $role, string $ip): RoleChangeResult
    {
        $refusal = $this->refuseUnlessOwner($actor);
        if ($refusal !== null) {
            return $refusal;
        }

        $target = $this->users->findByUsername(trim($targetUsername));
        if ($target === null) {
            return RoleChangeResult::refused('There is no account with that name. Check the spelling — it has to match exactly.');
        }

        // Guard: never let an owner remove themself. See the class docblock.
        if ($role === Role::Owner && $target->id === $actor->id) {
            return RoleChangeResult::refused(
                'You can\'t take the Owner role away from yourself. Make someone else an owner first, and they can remove yours.'
            );
        }

        $this->connection->beginTransaction();
        try {
            $rolesBefore = $this->roles->rolesFor($target->id);

            // Guard: never remove the LAST owner (only relevant when the
            // target really is one). countHoldersLocked takes a row lock, so
            // two racing revocations serialise here — the second one sees
            // the count the first one left behind (class docblock).
            if ($role === Role::Owner
                && in_array(Role::Owner, $rolesBefore, true)
                && $this->roles->countHoldersLocked(Role::Owner) <= 1
            ) {
                $this->connection->rollBack();

                return RoleChangeResult::refused(
                    'That is the only Owner the site has. Give someone else the Owner role before removing this one.'
                );
            }
            $revoked = $this->roles->revoke($target->id, $role);

            if ($revoked) {
                $this->auditLog->record(new AuditEntry(
                    actorUserId: $actor->id,
                    action: AuditAction::RoleRevoked,
                    subjectType: 'user',
                    subjectId: $target->id,
                    before: ['roles' => $this->roleNames($rolesBefore)],
                    after: ['roles' => $this->roleNames($this->roles->rolesFor($target->id))],
                    ip: $ip,
                ));
            }

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        if (!$revoked) {
            return RoleChangeResult::ok(sprintf('%s didn\'t have the %s role — nothing changed.', $target->username, $role->label()));
        }

        return RoleChangeResult::ok(sprintf('%s no longer has the %s role.', $target->username, $role->label()));
    }

    /**
     * The owner check both methods start with: read the ACTOR's roles fresh
     * from the database and refuse unless Owner is among them. Session state,
     * hidden form fields and anything else the browser sent play no part.
     */
    private function refuseUnlessOwner(User $actor): ?RoleChangeResult
    {
        if (!in_array(Role::Owner, $this->roles->rolesFor($actor->id), true)) {
            return RoleChangeResult::refused('Only an owner can change roles.');
        }

        return null;
    }

    /**
     * Role enums → their plain names, for the audit log's JSON snapshots.
     *
     * @param Role[] $roles
     * @return string[]
     */
    private function roleNames(array $roles): array
    {
        return array_map(static fn (Role $role): string => $role->value, $roles);
    }
}
