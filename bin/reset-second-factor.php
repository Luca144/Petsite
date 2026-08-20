<?php

declare(strict_types=1);

/**
 * Reset a staff account's authenticator app: php bin/reset-second-factor.php <username>
 *
 * @package Felkyo\Bin
 *
 * THE LOST-PHONE ESCAPE HATCH. If a staff member loses the phone with their
 * authenticator app AND has no recovery codes left, no web request can help
 * them — by design. This script, run on the server (shell access is the
 * credential), deletes their enrolment and their remaining recovery codes.
 * Their account is back to "not enrolled": at their next panel visit the
 * door takes their password and walks them through connecting a fresh app.
 *
 * It does NOT touch their password, their roles, or anyone else's anything,
 * and the reset is written to the audit log — an enrolment quietly vanishing
 * is exactly what the log exists to make loud.
 */

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\SecondFactorRepository;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Core\Database;
use Felkyo\Users\UserRepository;

// This is a command-line tool, not a page — refuse to run as one.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require dirname(__DIR__) . '/config/bootstrap.php';

$username = $argv[1] ?? '';
if ($username === '') {
    fwrite(STDERR, "Usage: php bin/reset-second-factor.php <username>\n");
    exit(1);
}

$connection = Database::connect($config['database']);
$users = new UserRepository($connection);
$secondFactors = new SecondFactorRepository($connection, new PasswordHasher());
$auditLog = new AuditLogRepository($connection);

$user = $users->findByUsername($username);
if ($user === null) {
    fwrite(STDERR, "There is no account named \"{$username}\". The name has to match exactly.\n");
    exit(1);
}

if ($secondFactors->findSecret($user->id) === null) {
    echo "{$user->username} has no authenticator app connected — nothing to reset.\n";
    exit(0);
}

// Reset and audit together, or not at all.
$connection->beginTransaction();
try {
    $secondFactors->reset($user->id);

    $auditLog->record(new AuditEntry(
        actorUserId: null,
        action: AuditAction::SecondFactorReset,
        subjectType: 'user',
        subjectId: $user->id,
        ip: 'cli',
    ));

    $connection->commit();
} catch (\Throwable $error) {
    $connection->rollBack();
    throw $error;
}

echo "Done. {$user->username}'s authenticator app and recovery codes are cleared.\n";
echo "The next time they enter the panel, the door will walk them through setting up a fresh app.\n";
