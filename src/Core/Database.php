<?php

declare(strict_types=1);

namespace Felkyo\Core;

use PDO;

/**
 * Creates the database connection for Felkyo Creatures.
 *
 * @package Felkyo\Core
 *
 * WHAT THIS CLASS IS: a small factory whose only job is to build a configured
 * PDO connection. PDO is PHP's built-in database layer; we use it (never the old
 * mysqli or mysql_* functions) because it supports safe "prepared statements",
 * which are how we avoid SQL-injection attacks (see CLAUDE.md section 5).
 *
 * HOW THIS FITS THE BIGGER PICTURE: nothing else in the app should build a PDO
 * connection by hand. Repositories receive the connection this class produces
 * and use it to run their queries. Keeping connection setup in one place means
 * the important safety options below are applied consistently, everywhere.
 */
final class Database
{
    /**
     * Build a ready-to-use database connection from the "database" section of
     * the config array (host, port, name, user, password, charset).
     *
     * We pass the config in rather than reading it here so this class has one
     * job (connecting) and stays easy to test with different settings.
     */
    public static function connect(array $databaseConfig): PDO
    {
        // The DSN ("Data Source Name") is the address string PDO uses to find the
        // database: which driver (mysql — MariaDB speaks the MySQL protocol),
        // which host and port, which database, and which character set.
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $databaseConfig['host'],
            $databaseConfig['port'],
            $databaseConfig['name'],
            $databaseConfig['charset']
        );

        // These options make PDO behave the safe, predictable way we want:
        $options = [
            // Turn database errors into exceptions we can catch, instead of PDO
            // quietly returning false and letting bugs slip through unnoticed.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Return rows as simple associative arrays (column-name => value),
            // which are the easiest shape for a beginner to read and use.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Use REAL prepared statements on the database server rather than
            // letting PDO fake them by substituting strings. Real prepared
            // statements are what actually protect us from SQL injection.
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO($dsn, $databaseConfig['user'], $databaseConfig['password'], $options);
    }
}
