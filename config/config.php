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
 * Settings come from the environment, with a sensible fallback so a fresh checkout
 * still runs. The two small helpers below are the only "machinery" in this file.
 */

/**
 * Read one setting from the environment, or null if it is not set anywhere.
 *
 * WHY THIS LOOKS IN THREE PLACES — this matters, so it is worth the paragraph.
 * PHP does not reliably copy real environment variables into $_ENV; whether it
 * does is controlled by a php.ini setting called "variables_order", which very
 * often does not include the "E". On your own machine that is invisible, because
 * the values come from the .env file and phpdotenv writes those into $_ENV itself.
 * But on a hosting platform there is no .env at all — the settings are real
 * environment variables, which only getenv() can be relied on to see.
 *
 * Reading just $_ENV would therefore work perfectly on your machine and silently
 * fail on the live server: every setting would fall back to its development
 * default, which includes leaving REGISTRATION OPEN on a site meant to be closed.
 * Checking all three sources is what makes one config file correct in both places.
 *
 * THE ORDER MATTERS TOO: a real environment variable wins over the .env file. On a
 * server, what the platform is configured to say must always beat a file that
 * happens to be lying around — and it also lets you try a production setting
 * locally for a moment without editing .env.
 */
$readEnv = static function (string $name): ?string {
    // getenv() sees real environment variables; it returns false (not null) when
    // there is none, so both "false" and "null" mean "not set" here.
    $value = getenv($name);

    if ($value === false || $value === '') {
        // Nothing in the real environment — fall back to what phpdotenv loaded
        // from the .env file.
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
    }

    if ($value === false || $value === null) {
        return null;
    }

    return (string) $value;
};

// "development" on your machine, "production" on the live server. Several settings
// below take their DEFAULT from this, so it is read once into a variable first.
$environment = $readEnv('APP_ENV') ?? 'development';

/**
 * Read a true/false setting from the environment, falling back to a default.
 *
 * Environment variables are always text, so "true"/"false" arrive as strings.
 * This turns them into real booleans in one obvious place instead of repeating
 * the same comparison at each setting.
 */
$readFlag = static function (?string $value, bool $default): bool {
    if ($value === null) {
        return $default;
    }

    return $value === 'true';
};

return [

    // General information about the running application.
    'app' => [
        'name' => 'Felkyo Creatures',
        'environment' => $environment,
        // When true, the app may show detailed errors. Turned off in production.
        'debug' => $readFlag($readEnv('APP_DEBUG'), true),

        // IS PUBLIC REGISTRATION OPEN?
        //
        // The deployed site is a CLOSED DEMO: it runs on a handful of seeded demo
        // accounts, has no real users, and holds no real personal data. So
        // registration is CLOSED by default in production and OPEN by default in
        // development, where you need to be able to create test accounts freely.
        //
        // Setting REGISTRATION_OPEN in the environment ("true" or "false")
        // overrides the default either way.
        //
        // IMPORTANT: opening registration on a live site means taking on real
        // responsibilities for real people's data. Read the security note in
        // docs/deployment-guide.md before you switch this on.
        'registration_open' => $readFlag(
            $readEnv('REGISTRATION_OPEN'),
            $environment !== 'production'
        ),

        // Show the "this is a demo, not a live service" banner? On by default in
        // production so nobody mistakes the deployed demo for a real service.
        // Set SHOW_DEMO_NOTICE=true locally if you want to see how it looks.
        'show_demo_notice' => $readFlag(
            $readEnv('SHOW_DEMO_NOTICE'),
            $environment === 'production'
        ),
    ],

    // How to connect to the database. The values come from .env so that no
    // password is ever committed to the repository (see CLAUDE.md section 6).
    'database' => [
        'host' => $readEnv('DB_HOST') ?? '127.0.0.1',
        'port' => $readEnv('DB_PORT') ?? '3306',
        'name' => $readEnv('DB_NAME') ?? 'felkyo',
        'user' => $readEnv('DB_USER') ?? 'root',
        'password' => $readEnv('DB_PASSWORD') ?? '',
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
        'rate_limit_item_disposal' => [
            'max_attempts' => 30,    // at most 30 sells or discards...
            'window_seconds' => 60,  // ...per minute, per IP. Matched to purchases
                                     // because selling is the same shape of action.
                                     // NOTE: this is anti-abuse, not the protection
                                     // against being paid twice — that lives in the
                                     // database (InventoryRepository::removeOne).
        ],
        'rate_limit_profile' => [
            'max_attempts' => 20,     // at most 20 profile saves...
            'window_seconds' => 3600, // ...per hour, per IP. A profile is edited
                                      // occasionally, not repeatedly, so a low
                                      // ceiling costs an honest player nothing.
        ],
        'rate_limit_bio' => [
            'max_attempts' => 20,     // at most 20 bio edits...
            'window_seconds' => 3600, // ...per hour, per IP (anti-abuse)
        ],
        'rate_limit_guestbook' => [
            'max_attempts' => 20,     // at most 20 guestbook signings...
            'window_seconds' => 3600, // ...per hour, per IP (anti-abuse; the
                                      // one-entry-per-creature rule is the main gate)
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
        // their last adoption, not from midnight. 86400 seconds is a full day; lower
        // it (e.g. to 60) if you want to try the flow repeatedly while developing.
        'adoption' => [
            'cooldown_seconds' => 86400,
        ],


        // The economy's safety rule, and the one number that enforces it.
        //
        // A shop must always sell a thing for MORE than it buys it back for. If
        // that were ever the other way round, a player could buy an item, sell it
        // straight back, and repeat until they were infinitely rich — currency
        // made out of nothing, which is the duplication problem CLAUDE.md names.
        //
        // But "not a loss" is not enough: the shopkeeper has to live off the
        // difference too. So we keep a real margin rather than a hair's breadth.
        // 0.80 means a shop never pays more than 80% of what it charges.
        //
        // WHERE THIS NUMBER CAME FROM: the artist's own design document. Across
        // every item she had already priced, the most generous buy-back was
        // Pumpkin Soup at 75% (60g to buy, 45g back). 80% therefore leaves her
        // room to be kind without any existing item breaking the rule.
        //
        // This same ceiling will also floor the NPC friendship discount when that
        // arrives (M13): however much an NPC likes you, the price they charge can
        // never fall to what they would pay you. One rule, both directions.
        'economy' => [
            'maximum_sell_fraction_of_price' => 0.80,
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

        // The guestbook. Visitors sign a creature's guestbook by CHOOSING one of
        // the messages below — there is no free typing anywhere in it. That single
        // design decision is what makes the guestbook safe: if nobody can write
        // their own words, there is nothing to spam, no abuse to moderate, and no
        // user text to filter. Each person may leave one entry per creature, and
        // may swap it for a different message once a day.
        //
        // CONTENT AS DATA: the key on the left is what gets stored in the database;
        // the text on the right is what players read. Reword the text here and every
        // existing entry that uses that key updates instantly — no database change.
        //
        // To ADD a message: add a line. To RETIRE one: delete the line — entries that
        // already used it keep working and fall back to a neutral wording.
        // Two rules for the keys: keep them short, lowercase and hyphenated, and
        // NEVER reuse a key for a different meaning — old entries would silently
        // start saying something their author never chose.
        'guestbook' => [
            // How long before someone may change their entry again. 86400 = one day.
            'edit_cooldown_seconds' => 86400,
            // How many entries a creature's page shows.
            'entries_shown' => 20,
            'messages' => [
                'what-a-sweetheart' => 'What a sweetheart!',
                'lovely-creature' => 'What a lovely creature.',
                'passing-through' => 'Just passing through — hello!',
                'made-me-smile' => 'This one made me smile today.',
                'well-cared-for' => 'You can tell this one is well cared for.',
                'cosy-corner' => 'Such a cosy little corner of the world.',
                // Write plain text here, never HTML entities like &rsquo; — the
                // templates escape everything on output, so an entity would show up
                // as literal "&rsquo;" on the page. A real ’ character is correct.
                'come-back-again' => 'I’ll come back to visit again.',
                'warm-wishes' => 'Warm wishes from a fellow wanderer.',
            ],
        ],
    ],

    // Content moderation. A simple list of words that user-written text (like a
    // creature's bio) may not contain. Matched whole-word and case-insensitively,
    // so "scam" does not trip on "scamper". This is a starting point — the Product
    // Owner can expand it. It is intentionally NOT profanity here; add what you need.
    'moderation' => [
        'blocked_words' => ['spam', 'scam', 'viagra'],
    ],

    // The avatars a player may choose. CONTENT, not code: adding one is a new
    // entry here plus a picture in public/assets/avatars/ — and from M2.4, a
    // panel screen. See docs/adding-avatars.md.
    //
    // Players NEVER upload an avatar. Accepting uploaded pictures would mean
    // moderating pictures, which is far harder than moderating text, needs
    // somebody awake to do it, and is the easiest route for something genuinely
    // harmful onto a site with children on it. A chosen set removes the problem
    // instead of managing it.
    //
    // The "name" is not decoration — it is what a screen reader announces and what
    // a player reads beside the picture when choosing. A grid of unlabelled
    // pictures is unusable without sight, so every avatar has one.
    //
    // Only one exists so far, which is fine: the mechanism is what M1.3 delivers,
    // and the artist adds faces as she draws them without anybody touching code.
    'avatars' => [
        'default' => ['name' => 'The wandering visitor', 'file' => 'default.png'],
    ],

    // How much a player may write about themselves on their profile. Kept short
    // on purpose: a profile is a greeting, not a page of prose, and every extra
    // character is more room for something that has to be read by a human if it
    // is ever reported. M1.4 hardens this field further.
    'profile' => [
        'max_about_length' => 300,
        'max_featured_creatures' => 6,
    ],

];
