<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Placeholder words for the library (M5), so its pages have something in
 * them before the artist writes the real ones.
 *
 * @package Felkyo\Seeds
 *
 * ONLY FILLS GAPS. A species' lore or a stage's blurb is written here only if
 * it is still empty, and the demo Gazette article only if no article has that
 * address yet — so running this never overwrites a single word the artist
 * typed in the panel. Safe to run any number of times.
 *
 *   C:\xampp\php\php.exe vendor/bin/phinx seed:run -s DemoLibrary
 *
 * Everything here is meant to be replaced: open the species in the panel and
 * write over it.
 */
final class DemoLibrary extends AbstractSeed
{
    /** species slug => [lore, [stage => blurb]] */
    private const SPECIES_WORDS = [
        'foxlen' => [
            "Nobody has ever seen a Foxlen arrive. One morning there is simply a warm patch on the windowsill that wasn't there before, and in the middle of it, curled nose to tail, a Foxlen.\n\nThey are said to follow the smell of baking bread across whole valleys. Old Felkyo stories claim that a house with a Foxlen in it never burns its toast, though nobody has managed to prove it either way.\n\nA grown Foxlen is a dignified creature, right up until somebody drops a crumb.",
            ['baby' => 'Mostly ears. Falls asleep mid-step.', 'juvenile' => 'Has discovered its tail, and is suspicious of it.', 'adult' => 'Elegant, unhurried, and always exactly where the sunbeam is.'],
        ],
        'mossling' => [
            "Mosslings grow where the forest floor stays damp and quiet. If you sit still long enough on an old stump, you may find that one of the cushions of moss beside you has been breathing all along.\n\nThey are shy, but they are not unfriendly. A Mossling that trusts you will leave a tiny sprig of clover on your doorstep, and then pretend it had nothing to do with it.\n\nIn a hard winter, Mosslings sleep under the snow and dream green dreams until spring.",
            ['baby' => 'A small, soft, very damp hello.', 'juvenile' => 'Starting to sprout. Proud of it.', 'adult' => 'A walking little garden, smelling faintly of rain.'],
        ],
        'pebblewing' => [
            "Pebblewings are made, the story goes, from the stones at the bottom of the brightest streams. They are heavier than they look and lighter than they feel, which is a riddle every Pebblewing owner eventually stops trying to solve.\n\nThey collect small shiny things — buttons, beads, the occasional lost key — and hide them with great care in places that are not hidden at all.\n\nOn clear nights a Pebblewing will sit very still and look at the stars, as if counting the ones it hasn't collected yet.",
            ['baby' => 'A pebble with ambitions.', 'juvenile' => 'Flaps a lot. Gets about a foot off the ground. Delighted.', 'adult' => 'Glides low over water, glittering like a skimmed stone.'],
        ],
    ];

    public function run(): void
    {
        $pdo = $this->getAdapter()->getConnection();

        foreach (self::SPECIES_WORDS as $slug => [$lore, $blurbs]) {
            $id = $pdo->prepare('SELECT id FROM species WHERE slug = :slug');
            $id->execute([':slug' => $slug]);
            $speciesId = $id->fetchColumn();
            if ($speciesId === false) {
                continue;
            }

            // Only where empty — never over the artist's own words.
            $pdo->prepare("UPDATE species SET lore = :lore WHERE id = :id AND (lore IS NULL OR lore = '')")
                ->execute([':lore' => $lore, ':id' => $speciesId]);

            foreach ($blurbs as $stage => $blurb) {
                $pdo->prepare(
                    "INSERT INTO species_stages (species_id, stage, blurb) VALUES (:id, :stage, :blurb)
                     ON DUPLICATE KEY UPDATE blurb = IF(blurb IS NULL OR blurb = '', VALUES(blurb), blurb)"
                )->execute([':id' => $speciesId, ':stage' => $stage, ':blurb' => $blurb]);
            }
        }

        $this->seedFirstArticle($pdo);
    }

    /** The Gazette's very first issue — only if it isn't there yet. */
    private function seedFirstArticle(\PDO $pdo): void
    {
        $exists = $pdo->prepare('SELECT 1 FROM articles WHERE slug = :slug');
        $exists->execute([':slug' => 'the-gazette-opens']);
        if ($exists->fetchColumn() !== false) {
            return;
        }

        $body = "Felkyo has a newspaper at last. It will be printed whenever there is something worth telling, which in Felkyo is more often than you would think.\n\n"
            . "Our first interview was meant to be with Ruinily, who keeps the shop. She agreed, and then spent the whole hour rearranging the jars on the top shelf and asking whether we had eaten. We had not. We left with a paper bag of something warm and no quotes at all.\n\n"
            . "We consider this a success.\n\n"
            . "In coming issues: who has been leaving clover on doorsteps, why the stream near the meadow has gone so quiet, and a full report on the great Foxlen-and-the-bread-basket incident.";

        $pdo->prepare(
            "INSERT INTO articles (slug, title, summary, body, status, published_at)
             VALUES ('the-gazette-opens', 'The Gazette Opens Its Doors', :summary, :body, 'published', NOW())"
        )->execute([':summary' => 'Felkyo finally has a newspaper. Our first interview did not go to plan.', ':body' => $body]);
    }
}
