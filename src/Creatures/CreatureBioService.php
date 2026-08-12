<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The rules for writing a creature's bio.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: validates a bio (length and blocked words) and saves it, refusing
 * anyone who is not the creature's owner.
 *
 * WHY THE OWNER CHECK IS HERE AS WELL AS IN THE CONTROLLER: a bio is the one place
 * a player types words that other players read, so it is worth more than one lock.
 * The controller refuses a stranger before we ever get here; this class refuses
 * again; and the repository's UPDATE names the owner in its WHERE clause so even a
 * mistake in both of those changes nothing. Three cheap layers, none of them
 * clever. From M1.4 this field carries real safety weight, and the layers are
 * already in place for it.
 *
 * The maximum length comes from config so it can be retuned in one place.
 */
final class CreatureBioService
{
    public function __construct(
        private CreatureRepository $creatures,
        private ContentFilter $filter,
        private int $maxLength,
    ) {
    }

    /**
     * Validate and save a new bio for a creature. $editorUserId is the person
     * doing the editing — they must be the owner. Returns a BioResult saying
     * whether it was saved, with a message to show either way.
     */
    public function updateBio(Creature $creature, int $editorUserId, string $bio): BioResult
    {
        if ($creature->ownerId !== $editorUserId) {
            return BioResult::rejected('You can only edit your own creature\'s bio.');
        }

        $bio = trim($bio);

        if (mb_strlen($bio) > $this->maxLength) {
            return BioResult::rejected("The bio must be no more than {$this->maxLength} characters.");
        }

        if ($this->filter->containsBlockedWord($bio)) {
            return BioResult::rejected('Please keep the bio friendly — some words in it are not allowed.');
        }

        // We hand the EDITOR's id to the repository, not the creature's owner id.
        // Passing the creature's own owner id would make the WHERE clause down
        // there compare a value to itself, which always matches — protection that
        // looks real in the code and does nothing in practice.
        $this->creatures->updateBio($creature->id, $editorUserId, $bio);

        return BioResult::saved('Bio saved.');
    }
}
