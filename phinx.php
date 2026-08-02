<?php

declare(strict_types=1);

/**
 * Phinx configuration for Felkyo Creatures.
 *
 * @package Felkyo\Config
 *
 * WHAT THIS IS: Phinx is the tool that creates and changes the database
 * structure through "migrations" (small, ordered, version-controlled scripts in
 * the migrations/ folder). This file tells Phinx where the migrations live and
 * how to connect to each database.
 *
 * WHY IT LOADS THE APP BOOTSTRAP: so the database credentials come from the same
 * .env file the app uses — never hardcoded here (see CLAUDE.md section 6).
 *
 * THE THREE ENVIRONMENTS:
 *   - development : your local "felkyo" database (the default).
 *   - testing     : the separate "felkyo_test" database the automated tests use.
 *   - production  : the live server (set up in Phase D; reads from env there).
 *
 * HOW TO RUN (from the project root):
 *   C:\xampp\php\php.exe vendor/robmorgan/phinx/bin/phinx migrate -e development
 *   C:\xampp\php\php.exe vendor/robmorgan/phinx/bin/phinx migrate -e testing
 */

// Loads Composer's autoloader and the .env file, and returns the settings array.
$config = require __DIR__ . '/config/bootstrap.php';
$database = $config['database'];

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
        'seeds' => __DIR__ . '/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',

        // Local development database (from .env).
        'development' => [
            'adapter' => 'mysql',
            'host' => $database['host'],
            'name' => $database['name'],
            'user' => $database['user'],
            'pass' => $database['password'],
            'port' => (int) $database['port'],
            'charset' => $database['charset'],
        ],

        // Separate database used only by the automated tests.
        'testing' => [
            'adapter' => 'mysql',
            'host' => $database['host'],
            'name' => 'felkyo_test',
            'user' => $database['user'],
            'pass' => $database['password'],
            'port' => (int) $database['port'],
            'charset' => $database['charset'],
        ],

        // Live server. In Phase D these come from the platform's environment
        // variables; for now they mirror development so the config is valid.
        'production' => [
            'adapter' => 'mysql',
            'host' => $database['host'],
            'name' => $database['name'],
            'user' => $database['user'],
            'pass' => $database['password'],
            'port' => (int) $database['port'],
            'charset' => $database['charset'],
        ],
    ],

    // Apply migrations in the order they were created (by their number prefix).
    'version_order' => 'creation',
];
