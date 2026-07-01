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
