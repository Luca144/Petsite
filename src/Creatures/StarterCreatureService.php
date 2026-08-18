<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Economy\InventoryRepository;
use Felkyo\Users\User;
use PDO;

/**
 * Gives a brand-new player their first creature — and something to give it.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the rule for "a new user receives a starter creature". It picks a
 * starter species and a friendly default name, then creates the creature owned by
 * that user. It is called just after a successful registration.
 *
 * The choices are made deterministically from the user's id (rather than at
 * random) so that the same user would always get the same starter — which also
 * makes the behaviour easy to test. Because there are several starter species and
 * several names, different players still get pleasant variety.
 *
 * WHY IT ALSO HANDS OUT TREATS (added in M2). A creature can be petted, and it can
 * be fed — but treats are bought with gems, and gems are earned by petting OTHER
 * people's creatures. So a player on their first minute had a creature, an empty
 * purse, an empty satchel, and a feeding section whose entire content was a
 * sentence explaining that they had nothing to feed with. That is a dead end on
 * the first screen (golden rule 3), and worse, it hides half of what a creature
 * IS behind an errand.
 *
 * A couple of treats fixes that: you can try feeding immediately, see what it
 * does, and only then go and earn more. Discoverable by doing, which is golden
 * rule 1 — if it needs explaining, it is not designed yet.
 *
 * They are found by SLUG, never by id: ids differ between a fresh install and a
 * migrated one, and a starter gift that hands out the wrong thing on the live
 * site would be very hard to notice.
 */
final class StarterCreatureService
{
    /**
     * The treats a new player starts with, by slug and how many. Two of the
     * everyday one and one of the comforting one — enough to try both kinds of
     * thing a treat can be, not enough to skip the game.
     */
    private const STARTER_TREATS = [
        'acorn-treat' => 2,
        'honey-treat' => 1,
    ];

    /**
     * @param string[] $starterNames The pool of default names (from config).
     */
    public function __construct(
        private SpeciesRepository $species,
        private CreatureRepository $creatures,
        private array $starterNames,
        // Optional so that older callers and tests that only care about the
        // creature keep working; when both are given, treats are handed out too.
        private ?PDO $connection = null,
        private ?InventoryRepository $inventory = null,
    ) {
    }

    /**
     * Create and return the starter creature for a newly registered user.
     */
    public function grantStarterTo(User $user): Creature
    {
        $starterSpecies = $this->species->findStarters();
        if ($starterSpecies === []) {
            // This only happens if the species were never seeded — a setup error,
            // so we fail loudly rather than silently create nothing.
            throw new \RuntimeException('No starter species are available to hand out.');
        }

        // Pick a species and a name using the user's id. The modulo (%) keeps the
        // choice inside the list no matter how large the id grows.
        $species = $starterSpecies[$user->id % count($starterSpecies)];
        $name = $this->starterNames[$user->id % count($this->starterNames)];

        $creature = $this->creatures->create($user->id, $species->id, $name);

        $this->grantStarterTreatsTo($user);

        return $creature;
    }

    /**
     * Put a few treats in a new player's satchel, so feeding is something they
     * can try rather than something they have to earn their way to.
     *
     * A missing treat is skipped rather than fatal. This runs during
     * REGISTRATION, and refusing somebody an account because a seed row is not
     * where it was expected would be a wildly disproportionate response to a
     * content problem. They would simply start with fewer treats.
     */
    private function grantStarterTreatsTo(User $user): void
    {
        if ($this->connection === null || $this->inventory === null) {
            return;
        }

        $lookup = $this->connection->prepare('SELECT id FROM items WHERE slug = :slug LIMIT 1');

        foreach (self::STARTER_TREATS as $slug => $howMany) {
            $lookup->execute([':slug' => $slug]);
            $itemId = $lookup->fetchColumn();

            if ($itemId === false) {
                continue;
            }

            for ($given = 0; $given < $howMany; $given++) {
                $this->inventory->addItem($user->id, (int) $itemId);
            }
        }
    }
}
