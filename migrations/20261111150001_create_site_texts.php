<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The site's words, as changed in the panel (or by clicking them in
 * editing mode).
 *
 * @package Felkyo\Migrations
 *
 * ONLY CHANGES ARE STORED. Every sentence's original lives in the template
 * that shows it ($this->t('key', 'The original words')). A row here replaces
 * it; deleting the row is "back to the original". So an empty table means
 * the site reads exactly as written — nothing can go missing.
 *
 * site_texts
 *   text_key            which sentence, e.g. "shore.walk-the-tideline", or
 *                       "message.<hash>" for a message the code sends
 *   body                the words to show instead
 *   original            (messages only) the pattern they replace
 *   updated_by_user_id  who changed it last (SET NULL if they leave)
 *   updated_at
 *
 * The whole table is read in one query per page (it holds only the changed
 * sentences, so it stays small).
 */
final class CreateSiteTexts extends AbstractMigration
{
    public function change(): void
    {
        $this->table('site_texts', ['id' => false, 'primary_key' => ['text_key'], 'signed' => false])
            ->addColumn('text_key', 'string', ['limit' => 120, 'null' => false])
            ->addColumn('body', 'text', ['null' => false])
            // For a message the code sends: its original pattern (like
            // 'Moved "%s" up.'), so a message on its way to the page can be
            // recognised without reading the code. NULL for template texts.
            ->addColumn('original', 'text', ['null' => true])
            ->addColumn('updated_by_user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('updated_by_user_id', 'users', 'id', ['delete' => 'SET NULL'])
            ->create();
    }
}
