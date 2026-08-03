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

    // Security and account policy. These are the "knobs" for how strict accounts
    // and anti-abuse limits are. Changing the feel (e.g. a longer minimum
    // password, or looser rate limits) is a one-line edit here — no code changes.
    'security' => [
        // Password rules. We use PHP's password_hash() with its default algorithm
        // (currently bcrypt). bcrypt ignores anything past 72 bytes, so we cap the
        // maximum there rather than silently accept characters that do nothing.
        'password_min_length' => 8,
        'password_max_length' => 72,

        // Username rules. The allowed characters (letters, numbers, _ and -) are
        // enforced by a clear rule in UserValidator.
        'username_min_length' => 3,
        'username_max_length' => 30,

        // Email rules (format is checked with PHP's built-in email validator).
        'email_max_length' => 255,

        // Rate limits, keyed per IP address. Each is "at most this many attempts
        // within this many seconds". They protect the state-changing public
        // endpoints against brute-force and spam (CLAUDE.md section 6).
        'rate_limit_login' => [
            'max_attempts' => 5,     // 5 failed logins...
            'window_seconds' => 900, // ...per 15 minutes, per IP
        ],
        'rate_limit_register' => [
            'max_attempts' => 3,      // 3 new accounts...
            'window_seconds' => 3600, // ...per hour, per IP
        ],
    ],

    // Gameplay tunables — the documented home for "knobs" so a beginner can
    // retune the game without touching game logic. Each increment adds the knobs
    // it needs here.
    'gameplay' => [

        // How a creature grows. A creature's life stage is worked out from its XP
        // (see GrowthCalculator): it is the highest stage whose XP requirement it
        // has reached. Every creature starts as a "baby" at 0 XP. The actual
        // earning of XP arrives in increment B.2 — these thresholds are the knob
        // that decides how much is needed to grow.
        'stage_xp_thresholds' => [
            'baby' => 0,
            'juvenile' => 100,
            'adult' => 300,
        ],

        // The pool of friendly default names a brand-new creature can be given
        // when a player first receives their starter. The player can be allowed to
        // rename it in a later increment.
        'starter_creature_names' => [
            'Pip', 'Biscuit', 'Clover', 'Marlow', 'Sage', 'Bramble', 'Dot', 'Fern',
        ],
    ],

];
