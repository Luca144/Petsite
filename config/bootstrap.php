<?php

declare(strict_types=1);

/**
 * Application bootstrap for Felkyo Creatures.
 *
 * @package Felkyo\Config
 *
 * WHAT THIS FILE IS: the shared start-up routine used by BOTH the website
 * (public/index.php) and the automated tests (tests/bootstrap.php). It does the
 * two things every entry point needs before it can do anything useful:
 *   1. Load Composer's autoloader, so our classes and the libraries are found.
 *   2. Load the .env file, so configuration and secrets are available.
 * It then returns the assembled configuration array (from config.php).
 *
 * It deliberately does NOT start a session or open the database — each caller
 * decides what it actually needs. Keeping bootstrap this small makes it easy to
 * understand what happens on every request.
 *
 * HOW THIS FITS THE BIGGER PICTURE: this is the "front door" of the code. If you
 * are ever tracing how a request begins, start reading here.
 */

$projectRoot = dirname(__DIR__);

// Composer generates this autoloader from composer.json. Requiring it means we
// never have to write manual `require` lines for our classes again. `_once`
// guards against loading it twice if two entry points chain together.
require_once $projectRoot . '/vendor/autoload.php';

// A missing .env file is the single most common first-run mistake. We catch it
// here and explain exactly how to fix it, rather than letting it surface later
// as a confusing "unknown database" error deep in the code.
if (!file_exists($projectRoot . '/.env')) {
    $message = 'Configuration error: no .env file found. '
        . 'Copy .env.example to .env and fill in your database details.';
    // Show the message whether we are running on the web or on the command line.
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        http_response_code(500);
        echo $message;
    }
    exit(1);
}

// phpdotenv reads the .env file and copies its values into PHP's environment
// ($_ENV). "createImmutable" means it will NOT overwrite a variable that is
// already set — this is important for the tests, which set the test database
// name first so they can never touch real data.
$dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
$dotenv->load();

// Hand back the fully assembled settings for the caller to use.
return require __DIR__ . '/config.php';
