<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use PDO;

/**
 * The settings overrides' only SQL (M2.8).
 *
 * @package Felkyo\Admin
 *
 * A tiny key/value table read once per request during boot. A missing
 * row means "the built-in value"; deleting a row IS the reset button.
 * Values travel as JSON so a list (the blocklist) and a number ride the
 * same column; everything is re-validated by SettingsCatalog on read.
 */
final class SiteSettingsRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Every stored override, decoded — the once-per-request read the
     * front controller lays over config.
     *
     * @return array<string, mixed> catalog key → decoded value
     */
    public function overrides(): array
    {
        $rows = $this->connection
            ->query('SELECT setting_key, value FROM site_settings')
            ->fetchAll() ?: [];

        $overrides = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $overrides[(string) $row['setting_key']] = $decoded;
            }
        }

        return $overrides;
    }

    /** Store (or replace) one override. $value must already be validated. */
    public function set(string $key, mixed $value): void
    {
        // One statement, insert-or-update, so two saves racing cannot
        // leave a duplicate-key error where a settings change should be.
        $statement = $this->connection->prepare(
            'INSERT INTO site_settings (setting_key, value, updated_at)
             VALUES (:key, :value, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        $statement->execute([':key' => $key, ':value' => json_encode($value)]);
    }

    /** Back to the built-in value: the override simply stops existing. */
    public function reset(string $key): void
    {
        $statement = $this->connection->prepare(
            'DELETE FROM site_settings WHERE setting_key = :key'
        );
        $statement->execute([':key' => $key]);
    }
}
