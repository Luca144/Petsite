<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gives the base species a line of flavour text (increment C.2).
 *
 * @package Felkyo\Migrations
 *
 * WHY THIS EXISTS: species flavour text is content. This fills in a short,
 * characterful line for each seeded species, shown on the creature page. Like the
 * species names, these lines are PLACEHOLDERS the Product Owner can rewrite (a
 * data change — an UPDATE).
 *
 * up()/down() because Phinx cannot auto-reverse data changes.
 */
final class SetSpeciesFlavourText extends AbstractMigration
{
    public function up(): void
    {
        $flavours = [
            'foxlen' => 'A shy woodland wanderer said to nap in the warmest corner of any room.',
            'mossling' => 'Small, green, and endlessly curious — it collects shiny pebbles and forgets where.',
            'pebblewing' => 'Quiet as a held breath; it hums an old tune when it thinks no one is listening.',
        ];

        foreach ($flavours as $slug => $flavour) {
            $statement = $this->getAdapter()->getConnection()->prepare(
                'UPDATE species SET flavour_text = :flavour WHERE slug = :slug'
            );
            $statement->execute([':flavour' => $flavour, ':slug' => $slug]);
        }
    }

    public function down(): void
    {
        $this->execute(
            "UPDATE species SET flavour_text = NULL
              WHERE slug IN ('foxlen', 'mossling', 'pebblewing')"
        );
    }
}
