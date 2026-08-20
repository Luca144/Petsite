<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use PDO;

/**
 * Reads and writes which users hold which staff roles.
 *
 * @package Felkyo\Admin
 *
 * WHAT THIS CLASS IS: the ONLY place that runs SQL for the user_roles table.
 * The rest of the app asks it questions ("what roles does this user hold?")
 * and never sees the table.
 *
 * A SECURITY NOTE THAT SHAPES EVERY METHOD HERE: roles are read fresh from
 * the database on every request and NEVER cached in the session. That is what
 * makes revoking a role take effect on the very next request — a session that
 * remembered "I am an owner" would keep being an owner until it logged out,
 * which is exactly the window an incident does not need.
 */
final class RoleRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Every role this user holds, as Role enum values. An ordinary player
     * gets an empty array — which is also the answer the AdminGate turns
     * into "this page does not exist for you".
     *
     * @return Role[]
     */
    public function rolesFor(int $userId): array
    {
        // Fetch just the role names; the unique (user_id, role) index makes
        // this a short indexed read even as the site grows.
        $statement = $this->connection->prepare(
            'SELECT role FROM user_roles WHERE user_id = :user_id'
        );
        $statement->execute([':user_id' => $userId]);

        $roles = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $storedName) {
            // tryFrom quietly skips a value that is not on the allow-list.
            // That can only happen if someone edited the table by hand — and
            // an unknown role name granting nothing is the safe reading of it.
            $role = Role::tryFrom((string) $storedName);
            if ($role !== null) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * Give a user a role. Returns true if the role was newly granted, false
     * if they already held it.
     *
     * INSERT IGNORE leans on the unique (user_id, role) index: the same grant
     * arriving twice — a double-click, or two requests racing — is a no-op,
     * never a duplicate row. That index is what keeps "how many owners are
     * left?" an honest count.
     */
    public function grant(int $userId, Role $role, ?int $grantedByUserId): bool
    {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role, granted_by_user_id)
             VALUES (:user_id, :role, :granted_by)'
        );
        $statement->execute([
            ':user_id' => $userId,
            ':role' => $role->value,
            ':granted_by' => $grantedByUserId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Take a role away. Returns true if a role was actually removed, false
     * if the user did not hold it.
     */
    public function revoke(int $userId, Role $role): bool
    {
        $statement = $this->connection->prepare(
            'DELETE FROM user_roles WHERE user_id = :user_id AND role = :role'
        );
        $statement->execute([':user_id' => $userId, ':role' => $role->value]);

        return $statement->rowCount() > 0;
    }

    /**
     * Lock every row of one role and return how many users hold it.
     *
     * This exists for exactly one caller: the last-owner guard in
     * RoleAssignmentService. FOR UPDATE makes the count trustworthy inside a
     * transaction — two revocations racing each other both try to lock these
     * rows, one waits for the other, and the second one sees the count the
     * first one left behind. Without the lock, both could count "2 owners",
     * both proceed, and the site would end up with none.
     *
     * MUST be called inside an open transaction, or the lock releases
     * immediately and protects nothing.
     */
    public function countHoldersLocked(Role $role): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM user_roles WHERE role = :role FOR UPDATE'
        );
        $statement->execute([':role' => $role->value]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Everyone who holds any role, with their username, for the roles screen:
     * one row per (user, role), ordered by name so the list is scannable.
     *
     * The JOIN fetches the usernames in the same query — the alternative (one
     * lookup per holder) would be an N+1 for no reason.
     *
     * @return array<int, array{user_id: int, username: string, role: Role, granted_at: string}>
     */
    public function allAssignments(): array
    {
        $statement = $this->connection->query(
            'SELECT ur.user_id, u.username, ur.role, ur.granted_at
               FROM user_roles ur
               JOIN users u ON u.id = ur.user_id
              ORDER BY u.username, ur.role'
        );

        $assignments = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $role = Role::tryFrom((string) $row['role']);
            if ($role === null) {
                // A hand-edited unknown role name: skip it rather than crash
                // the one screen that could be used to clean it up.
                continue;
            }

            $assignments[] = [
                'user_id' => (int) $row['user_id'],
                'username' => (string) $row['username'],
                'role' => $role,
                'granted_at' => (string) $row['granted_at'],
            ];
        }

        return $assignments;
    }
}
