<?php

declare(strict_types=1);

/**
 * Test bootstrap for Felkyo Creatures.
 *
 * @package Felkyo\Tests
 *
 * WHAT THIS IS: the start-up file PHPUnit runs once before any tests. It sets up
 * the same environment as the website, but with one crucial safety change: it
 * forces the database name to "felkyo_test".
 *
 * WHY THE FORCED TEST DATABASE MATTERS: tests create, change and delete rows. If
 * they ran against the real "felkyo" database they would destroy real data. By
 * overriding the name here — before the app's config is read — every test runs
 * against a separate, disposable database instead. This guard is intentional and
 * must not be removed.
 */

$projectRoot = dirname(__DIR__);

// Load the autoloader and the .env file exactly as the website does.
require $projectRoot . '/config/bootstrap.php';

// Force the test database name. The app's config reads $_ENV['DB_NAME'], so
// overwriting it here guarantees the tests point at felkyo_test regardless of
// what .env says. We set it in all three places PHP exposes the environment so
// nothing can read the old value.
$_ENV['DB_NAME'] = 'felkyo_test';
$_SERVER['DB_NAME'] = 'felkyo_test';
putenv('DB_NAME=felkyo_test');

// Make sure the test database's structure is up to date before any test runs, by
// applying the Phinx migrations to the "testing" environment. This keeps the test
// suite self-contained: you never have to remember to migrate felkyo_test by hand.
//
// We run Phinx with the SAME PHP that is running the tests (PHP_BINARY), so this
// works on any machine without hardcoding a path. Phinx only applies migrations
// that are not already applied, so on repeat runs this is quick and harmless.
$phinxCommand = escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg($projectRoot . '/vendor/robmorgan/phinx/bin/phinx')
    . ' migrate -e testing'
    . ' -c ' . escapeshellarg($projectRoot . '/phinx.php');

exec($phinxCommand . ' 2>&1', $migrationOutput, $migrationExitCode);

// If migrating the test database failed, stop now with a clear message rather
// than letting every test fail confusingly against a half-built database.
if ($migrationExitCode !== 0) {
    fwrite(STDERR, "Could not prepare the test database (felkyo_test).\n");
    fwrite(STDERR, "Is MariaDB running? Phinx said:\n");
    fwrite(STDERR, implode("\n", $migrationOutput) . "\n");
    exit(1);
}
