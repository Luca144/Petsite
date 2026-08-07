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

// WHERE CONFIGURATION COMES FROM, IN TWO DIFFERENT PLACES:
//
//   - On your own machine, it comes from a .env file in the project root.
//   - On a hosting platform there is NO .env file. The platform supplies the
//     settings as real environment variables instead — that is the whole point of
//     keeping configuration out of the code (CLAUDE.md section 6).
//
// So we load .env when it exists and carry on quietly when it does not.
if (file_exists($projectRoot . '/.env')) {
    // "createImmutable" will NOT overwrite a variable that is already set. That is
    // what lets the tests force the test database name before this runs, so they
    // can never touch real data.
    Dotenv\Dotenv::createImmutable($projectRoot)->load();
} elseif (getenv('APP_ENV') === false && !isset($_ENV['APP_ENV'])) {
    // No .env file AND nothing configured in the environment either. On a fresh
    // checkout this is the single most common first-run mistake, so we say exactly
    // how to fix it rather than letting it surface later as a confusing "unknown
    // database" error deep in the code.
    $message = 'Configuration error: no .env file found and no APP_ENV set. '
        . 'On your own machine: copy .env.example to .env and fill in your database '
        . 'details. On a server: set the environment variables (see '
        . 'docs/deployment-guide.md).';

    // Show the message whether we are running on the web or on the command line.
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
    } else {
        http_response_code(500);
        echo $message;
    }
    exit(1);
}

// Hand back the fully assembled settings for the caller to use.
return require __DIR__ . '/config.php';
