<?php

declare(strict_types=1);

/**
 * Grant a staff role from the command line: php bin/grant-role.php <username> <role>
 *
 * @package Felkyo\Bin
 *
 * WHY THIS SCRIPT EXISTS: roles are assignable only by an owner, through the
 * panel — but the FIRST owner cannot be made that way, because making one
 * needs an owner already. This script is the bootstrap: it runs on the server
 * itself (like migrations do), which means whoever runs it already holds the
 * highest credential the site has — shell access. There is deliberately NO
 * web path that can do what this script does.
 *
 * It writes the same audit entry a panel grant would, with a null actor and
 * ip 'cli', so the log's first line is the founding of the first owner.
 *
 * This script only GRANTS. Taking roles away is an owner's decision made in
 * the panel, where the last-owner guard lives — a revoke on the command line
 * would be a way around that guard.
 */

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\Role;
use Felkyo\Admin\RoleRepository;
use Felkyo\Core\Database;
use Felkyo\Users\UserRepository;

// This is a command-line tool, not a page — refuse to run as one.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/config/bootstrap.php';

$username = $argv[1] ?? '';
$roleName = $argv[2] ?? '';

if ($username === '' || $roleName === '') {
    $roleList = implode(', ', array_map(static fn (Role $role): string => $role->value, Role::cases()));
    fwrite(STDERR, "Usage: php bin/grant-role.php <username> <role>\n");
    fwrite(STDERR, "Roles: {$roleList}\n");
    exit(1);
}

// The same allow-list every other path uses — the command line does not get
// to invent a fifth role either.
$role = Role::fromFormValue($roleName);
if ($role === null) {
    fwrite(STDERR, "\"{$roleName}\" isn't a role. The roles are: owner, moderator, artist, coder.\n");
    exit(1);
}

$connection = Database::connect($config['database']);
$users = new UserRepository($connection);
$roles = new RoleRepository($connection);
$auditLog = new AuditLogRepository($connection);

$user = $users->findByUsername($username);
if ($user === null) {
    fwrite(STDERR, "There is no account named \"{$username}\". The name has to match exactly.\n");
    exit(1);
}

// Grant and audit together, exactly as the panel would.
$connection->beginTransaction();
try {
    $granted = $roles->grant($user->id, $role, null);

    if ($granted) {
        $auditLog->record(new AuditEntry(
            actorUserId: null,
            action: AuditAction::RoleGranted,
            subjectType: 'user',
            subjectId: $user->id,
            after: ['roles' => array_map(
                static fn (Role $held): string => $held->value,
                $roles->rolesFor($user->id)
            )],
            ip: 'cli',
        ));
    }

    $connection->commit();
} catch (\Throwable $error) {
    $connection->rollBack();
    throw $error;
}

if (!$granted) {
    echo "{$user->username} already had the {$role->label()} role — nothing changed.\n";
    exit(0);
}

echo "{$user->username} is now: {$role->label()}. ({$role->description()})\n";
echo "They will be asked to set up an authenticator app the first time they enter the panel at /admin.\n";
