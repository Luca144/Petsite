<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * M4 — selectable themes, and the easy-reading mode.
 *
 * @package Felkyo\Migrations
 *
 * themes          a named set of colour-token overrides (JSON, only the
 *                 tokens ThemeTokens allows), plus whether it switches on
 *                 easy-reading type. Draft until published from the panel —
 *                 and the panel refuses to publish one that fails contrast.
 * users.theme_id  the player's choice; NULL = the site's own Lamplight look.
 *
 * Three starter themes are inserted, each pre-checked against every
 * contrast rule in ThemeTokens.
 */
final class CreateThemes extends AbstractMigration
{
    public function up(): void
    {
        $this->table('themes', ['signed' => false])
            ->addColumn('slug', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 40, 'null' => false])
            ->addColumn('tokens', 'text', ['null' => false])
            ->addColumn('easy_reading', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 10, 'default' => 'draft', 'null' => false])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('users')
            ->addColumn('theme_id', 'integer', ['signed' => false, 'null' => true])
            // Deleting a theme sends its players back to the default look.
            ->addForeignKey('theme_id', 'themes', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
            ->update();

        $this->table('themes')->insert([
            [
                'slug' => 'moonlit', 'name' => 'Moonlit', 'easy_reading' => 0, 'status' => 'published',
                'tokens' => json_encode([
                    'parchment' => '#E6EAF2', 'panel' => '#D3DAE8', 'purple' => '#4A4F9E', 'plum' => '#232845',
                    'plum-deep' => '#151A33', 'gold' => '#9FB3D9', 'gold-lit' => '#C9D8F2', 'border' => '#8E9AB8',
                    'ink-soft' => '#454B6B',
                ]),
            ],
            [
                'slug' => 'rosehip', 'name' => 'Rosehip', 'easy_reading' => 0, 'status' => 'published',
                'tokens' => json_encode([
                    'parchment' => '#F6E3DF', 'panel' => '#EDCFC8', 'purple' => '#9C2F4E', 'plum' => '#4A1E2C',
                    'plum-deep' => '#33121D', 'gold' => '#D98E5F', 'gold-lit' => '#F2B98F', 'border' => '#C49A8F',
                    'ink-soft' => '#6B3A47',
                ]),
            ],
            [
                // The site's own colours with easy-reading type: the dyslexia-
                // friendly mode, offered as its own theme (M4.3).
                'slug' => 'easy-reading', 'name' => 'Easy reading', 'easy_reading' => 1, 'status' => 'published',
                'tokens' => json_encode([]),
            ],
        ])->saveData();
    }

    public function down(): void
    {
        $this->table('users')->dropForeignKey('theme_id')->removeColumn('theme_id')->update();
        $this->table('themes')->drop()->save();
    }
}
