<?php

declare(strict_types=1);

/**
 * Central configuration for Felkyo Creatures.
 *
 * @package Felkyo\Config
 *
 * WHAT THIS FILE IS: the single place that assembles the application's settings
 * into one plain array. Secrets (database password, etc.) are read from the
 * environment (loaded from the .env file by bootstrap.php) — never written here,
 * so this file is safe to commit.
 *
 * HOW THIS FITS THE BIGGER PICTURE: as the game grows, this is also where the
 * "tunable values" live — cooldown lengths, XP thresholds, daily limits and so
 * on. Keeping every knob here means changing how the game feels is a one-line
 * edit in a documented place, not a hunt through the code. Increments add their
 * own knobs under the "gameplay" section as the features that need them arrive.
 *
 * We read settings from $_ENV (populated from .env) with a sensible fallback so
 * a fresh checkout still runs. Reading $_ENV directly keeps this file obvious —
 * there is no magic helper to learn.
 */

return [

    // General information about the running application.
    'app' => [
        'name' => 'Felkyo Creatures',
        // "development" on your machine, "production" on the live server.
        'environment' => $_ENV['APP_ENV'] ?? 'development',
        // When true, the app may show detailed errors. Turned off in production.
        'debug' => ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
    ],

    // How to connect to the database. The values come from .env so that no
    // password is ever committed to the repository (see CLAUDE.md section 6).
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'name' => $_ENV['DB_NAME'] ?? 'felkyo',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        // utf8mb4 is the full Unicode character set — it stores emoji and every
        // language correctly, which a friendly creature site will want.
        'charset' => 'utf8mb4',
    ],

    // Gameplay tunables (cooldowns, XP thresholds, limits) will be added here as
    // the increments that use them are built. Keeping them all in this one
    // section is deliberate: it is the documented home for "knobs" so a beginner
    // can retune the game without touching game logic.
    'gameplay' => [
        // (empty for now — populated from increment B.1 onwards)
    ],

];
