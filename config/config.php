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
        'rate_limit_pet' => [
            'max_attempts' => 20,    // at most 20 pet actions...
            'window_seconds' => 60,  // ...per minute, per IP (anti-abuse; the
                                     // per-creature cooldown below is the main gate)
        ],
        'rate_limit_adopt' => [
            'max_attempts' => 10,     // at most 10 adoption attempts...
            'window_seconds' => 3600, // ...per hour, per IP (anti-abuse; the
                                      // once-per-day cooldown is the main gate)
        ],
        'rate_limit_explore' => [
            'max_attempts' => 30,    // at most 30 explore clicks...
            'window_seconds' => 60,  // ...per minute, per IP (anti-abuse; the
                                     // per-visit click limit is the main gate)
        ],
        'rate_limit_purchase' => [
            'max_attempts' => 30,    // at most 30 purchases...
            'window_seconds' => 60,  // ...per minute, per IP (anti-abuse)
        ],
        'rate_limit_bio' => [
            'max_attempts' => 20,     // at most 20 bio edits...
            'window_seconds' => 3600, // ...per hour, per IP (anti-abuse)
        ],
    ],

    // Gameplay tunables — the documented home for "knobs" so a beginner can
    // retune the game without touching game logic. Each increment adds the knobs
    // it needs here.
    'gameplay' => [

        // How a creature grows (see GrowthCalculator). Both a creature's LEVEL and
        // its life STAGE are worked out from its XP, so XP is the single source of
        // truth. These are the knobs that decide how fast growth feels.
        'growth' => [
            // XP needed for each level. level = (xp / xp_per_level) + 1, so with 20
            // here a creature gains a level for every 20 XP it earns.
            'xp_per_level' => 20,
            // The level at which each life stage begins. A creature's stage is the
            // highest one whose start level it has reached.
            'stage_start_levels' => [
                'baby' => 1,
                'juvenile' => 3,
                'adult' => 6,
            ],
        ],

        // Petting — the core interaction. Each successful pet raises happiness and
        // grants XP, and the same person can only pet the same creature again once
        // the cooldown has passed. Tune these to change how the game feels.
        // (The cooldown is short by default so the loop is easy to try out; raise
        // it for a slower, calmer game.)
        'petting' => [
            'cooldown_seconds' => 30,
            'happiness_per_pet' => 1,
            'xp_per_pet' => 20,
        ],

        // Daily adoption — a player can adopt one new creature per "day" from the
        // pool of adoptable species. "Once per day" is measured as a cooldown from
        // their last adoption. (Short-ish default so it is easy to try; a real day
        // is 86400 seconds — raise it to that for once-per-calendar-day feel.)
        'adoption' => [
            'cooldown_seconds' => 86400,
        ],

        // Exploration areas. Each area is CONTENT defined as data, so adding a
        // second area is a new entry here (plus a background image) — not new code.
        // An area has clickable "spots" (positions on its scene, as percentages)
        // and a "loot" table of weighted rewards. When a spot is clicked, one
        // reward is chosen at random, weighted by its "weight" (higher = more
        // likely). Each visit allows a limited number of clicks, which refreshes
        // after the window passes.
        'exploration' => [
            'clicks_per_visit' => 5,
            'window_seconds' => 3600, // the click allowance refreshes after this
            'areas' => [
                'whispering-wood' => [
                    'name' => 'The Whispering Wood',
                    'description' => 'A hush of amber trees where small things rustle just out of sight.',
                    // Clickable spots, positioned by percentage across the scene.
                    'spots' => [
                        ['x' => 18, 'y' => 60],
                        ['x' => 40, 'y' => 42],
                        ['x' => 63, 'y' => 66],
                        ['x' => 80, 'y' => 48],
                        ['x' => 52, 'y' => 78],
                    ],
                    // Weighted rewards. Weights are relative and need not total 100.
                    // "type" says what is granted: "nothing", or a new "creature".
                    // (Currency and item rewards can be added here in later increments.)
                    'loot' => [
                        ['type' => 'nothing', 'weight' => 85, 'message' => 'Only leaves and a soft wind. Nothing this time.'],
                        ['type' => 'creature', 'weight' => 15, 'message' => 'Something small blinks in the undergrowth — a new creature follows you home!'],
                    ],
                ],
            ],
        ],

        // The pool of friendly default names a new creature can be given — used
        // for a player's starter, for adopted creatures, and for exploration finds.
        // Players may be allowed to rename in a later increment.
        'creature_names' => [
            'Pip', 'Biscuit', 'Clover', 'Marlow', 'Sage', 'Bramble', 'Dot', 'Fern',
        ],

        // How many recent public creatures the "browse" page shows.
        'browse_recent_limit' => 12,

        // The single in-game currency. A creature's OWNER earns this each time
        // someone ELSE pets their creature (the petting cooldown limits how often,
        // so it can't be farmed). "name" is only the label shown to players.
        'currency' => [
            'name' => 'coins',
            'per_pet' => 5,
        ],

        // The longest a creature's bio (written by its owner) may be.
        'bio_max_length' => 500,
    ],

    // Content moderation. A simple list of words that user-written text (like a
    // creature's bio) may not contain. Matched whole-word and case-insensitively,
    // so "scam" does not trip on "scamper". This is a starting point — the Product
    // Owner can expand it. It is intentionally NOT profanity here; add what you need.
    'moderation' => [
        'blocked_words' => ['spam', 'scam', 'viagra'],
    ],

];
