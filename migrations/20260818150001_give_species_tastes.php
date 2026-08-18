<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gives each species something it adores and something it would rather not.
 *
 * @package Felkyo\Migrations
 *
 * WHY: feeding was arithmetic. Three treats, three sets of numbers, and the only
 * question was which number was biggest — so "which treat" was a calculation, not
 * a choice about a creature. A creature that has a favourite is a creature with a
 * preference, and a preference is the smallest possible amount of character.
 *
 * TWO COLUMNS, AND WHY THEY ARE FOREIGN KEYS. Elsewhere in this codebase content
 * refers to items by SLUG — the exploration loot tables do, because they live in a
 * config file a person edits, and a slug means the same thing on every install.
 * These are different: they are a relationship between two database tables, and
 * the database can enforce that a taste points at an item that really exists. A
 * typo becomes an error at the moment somebody makes it rather than a preference
 * that silently never applies.
 *
 * ON DELETE SET NULL, deliberately: retiring an item should quietly leave the
 * species with no favourite, not refuse the deletion or leave a dangling id.
 *
 * WHAT THE DISLIKE IS NOT. It is not a punishment and it does not take anything
 * away. A creature offered something it dislikes still eats it, still gets a
 * little happier, and makes a face about it — the fun is the face. There is no
 * arrangement of data here that can make a creature worse off than before it was
 * fed, and there should never be (golden rule 11: when in doubt, gentler).
 */
final class GiveSpeciesTastes extends AbstractMigration
{
    public function up(): void
    {
        $this->table('species')
            ->addColumn('favourite_item_id', 'integer', [
                'signed' => false,
                'null' => true,
                'after' => 'gem_price',
            ])
            ->addColumn('disliked_item_id', 'integer', [
                'signed' => false,
                'null' => true,
                'after' => 'favourite_item_id',
            ])
            ->addForeignKey('favourite_item_id', 'items', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('disliked_item_id', 'items', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
            ])
            ->update();

        // The three starter species each adore a different treat and turn their
        // nose up at a different one, so no two of them feel the same to look
        // after. PLACEHOLDER pairings in exactly the sense the prices are — the
        // artist owns these, and they are the kind of thing worth arguing about
        // over tea rather than deciding in a migration.
        //
        // Matched by slug on both sides, because ids differ between a fresh
        // install and a migrated one and this file has to mean the same on both.
        $tastes = [
            'foxlen'     => ['loves' => 'honey-treat',      'dislikes' => 'chamomile-bundle'],
            'mossling'   => ['loves' => 'chamomile-bundle', 'dislikes' => 'acorn-treat'],
            'pebblewing' => ['loves' => 'acorn-treat',      'dislikes' => 'honey-treat'],
        ];

        foreach ($tastes as $speciesSlug => $taste) {
            $this->execute(sprintf(
                "UPDATE species
                    SET favourite_item_id = (SELECT id FROM items WHERE slug = '%s'),
                        disliked_item_id  = (SELECT id FROM items WHERE slug = '%s')
                  WHERE slug = '%s'",
                $taste['loves'],
                $taste['dislikes'],
                $speciesSlug
            ));
        }
    }

    public function down(): void
    {
        $this->table('species')
            ->dropForeignKey('favourite_item_id')
            ->dropForeignKey('disliked_item_id')
            ->update();

        $this->table('species')
            ->removeColumn('favourite_item_id')
            ->removeColumn('disliked_item_id')
            ->update();
    }
}
