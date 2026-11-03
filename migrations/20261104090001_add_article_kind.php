<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Site updates, the little blog Skerro asked for (2026-08-30): a picture on
 * the left and a few lines about what's new, the latest two on the home page
 * and all of them at /updates.
 *
 * @package Felkyo\Migrations
 *
 * An update is written exactly like a Gazette article, so it IS one, with a
 * `kind`: 'gazette' (stories about the people of Felkyo, in the library) or
 * 'update' (news about the site itself). Same editor, same pictures.
 */
final class AddArticleKind extends AbstractMigration
{
    public function change(): void
    {
        $this->table('articles')
            ->addColumn('kind', 'string', ['limit' => 10, 'default' => 'gazette', 'null' => false, 'after' => 'slug'])
            // Each list (Gazette, updates) is "published, of this kind, newest first".
            ->addIndex(['kind', 'status', 'published_at'])
            ->update();
    }
}
