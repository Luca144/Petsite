<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * A gentle first-run welcome (M7): Ruinily, in a speech bubble on the home
 * page, showing a new player around — one short step at a time, with a
 * permanent "don't show me this again". No forced tutorial.
 *
 * @package Felkyo\Migrations
 *
 * welcome_steps              what she says, in order, each with an optional
 *                            link to the place she's talking about. Edited in
 *                            the panel (/admin/welcome).
 * users.welcome_dismissed_at set the moment a player says "don't show me this
 *                            again" (or finishes); the bubble never returns.
 */
final class CreateWelcome extends AbstractMigration
{
    public function up(): void
    {
        $this->table('welcome_steps', ['signed' => false])
            ->addColumn('position', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('words', 'string', ['limit' => 300, 'null' => false])
            ->addColumn('link_href', 'string', ['limit' => 120, 'null' => true])
            ->addColumn('link_label', 'string', ['limit' => 60, 'null' => true])
            ->addIndex(['position'])
            ->create();

        $this->table('users')
            ->addColumn('welcome_dismissed_at', 'datetime', ['null' => true])
            ->update();

        $this->table('welcome_steps')->insert([
            ['position' => 1, 'words' => 'Oh! A new face. Welcome to Felkyo, love — come in out of the stars. I\'m Ruinily, I keep the shop.', 'link_href' => null, 'link_label' => null],
            ['position' => 2, 'words' => 'That little one below is yours. Pet them, feed them a treat, play a game — they\'ll grow up before you know it.', 'link_href' => '/creatures', 'link_label' => 'See your creatures'],
            ['position' => 3, 'words' => 'You earn gems by petting other people\'s creatures, too. Spend them in my shop — I\'ve always got the kettle on.', 'link_href' => '/shop', 'link_label' => 'Visit the shop'],
            ['position' => 4, 'words' => 'Curious what else lives here? The library has a book about every creature in Felkyo.', 'link_href' => '/library', 'link_label' => 'Open the library'],
            ['position' => 5, 'words' => 'That\'s the lot. Off you go and have a wander — and come and see me any time.', 'link_href' => '/explore', 'link_label' => 'Go exploring'],
        ])->saveData();
    }

    public function down(): void
    {
        $this->table('users')->removeColumn('welcome_dismissed_at')->update();
        $this->table('welcome_steps')->drop()->save();
    }
}
