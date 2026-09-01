<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The settings overrides (M2.8): panel-tuned values on top of config.
 *
 * @package Felkyo\Migrations
 *
 * Config stays where every value is DEFINED, explained and defaulted;
 * this table holds only what the panel has CHANGED. A missing row means
 * "the built-in value" — so a fresh install has an empty table, deleting
 * a row IS the "back to built-in" button, and the file that explains
 * every knob never goes stale.
 *
 * `setting_key` is a catalog key (SettingsCatalog), NOT a config path:
 * only keys the catalog names are ever read, so a hand-inserted row for
 * some other key is inert. `value` is JSON, and it is re-validated
 * through the catalog's bounds on every read — a smuggled out-of-range
 * number is ignored, not applied.
 */
final class CreateSiteSettings extends AbstractMigration
{
    public function change(): void
    {
        $this->table('site_settings', ['id' => false, 'primary_key' => ['setting_key'], 'signed' => false])
            ->addColumn('setting_key', 'string', ['limit' => 60, 'null' => false])
            ->addColumn('value', 'text', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->create();
    }
}
