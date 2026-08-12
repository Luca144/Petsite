<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Safety\TextGuard;

/**
 * The rules for writing a creature's bio.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: checks a bio and saves it, refusing anyone who is not the
 * creature's owner.
 *
 * WHY THE OWNER CHECK IS HERE AS WELL AS IN THE CONTROLLER: a bio is one of only
 * three places a player types words that other players read, so it is worth more
 * than one lock. The controller refuses a stranger before we get here; this class
 * refuses again; and the repository's UPDATE names the owner in its WHERE clause
 * so even a mistake in both changes nothing. Three cheap layers, none clever.
 *
 * WHAT THE TEXT ITSELF IS CHECKED FOR (M1.4): length, hidden characters, contact
 * details and blocked words — all of it in TextGuard, which is shared with account
 * names and profile about texts. Sharing it is the point: three separate sets of
 * rules would mean three different sets of gaps, and the gap nobody remembered
 * would be the one that mattered.
 *
 * A BIO IS THE HIGHEST-RISK OF THE THREE FIELDS. It is long enough to hold a whole
 * approach, and long enough to bury a contact detail in the middle of something
 * friendly. It is also the one that can be hidden pending review when reported,
 * which a name cannot be.
 */
final class CreatureBioService
{
    public function __construct(
        private CreatureRepository $creatures,
        private TextGuard $textGuard,
        private int $maxLength,
    ) {
    }

    /**
     * Check and save a new bio for a creature. $editorUserId is the person doing
     * the editing — they must be the owner. Returns a BioResult saying whether it
     * was saved, with a message to show either way.
     */
    public function updateBio(Creature $creature, int $editorUserId, string $bio): BioResult
    {
        if ($creature->ownerId !== $editorUserId) {
            return BioResult::rejected('You can only edit your own creature\'s bio.');
        }

        $guarded = $this->textGuard->checkLongText($bio, $this->maxLength);

        if (!$guarded->isAccepted()) {
            return BioResult::rejected($guarded->message());
        }

        // We hand the EDITOR's id to the repository, not the creature's owner id.
        // Passing the creature's own owner id would make the WHERE clause down
        // there compare a value to itself, which always matches — protection that
        // looks real in the code and does nothing in practice.
        //
        // And we save the guard's cleaned value, never the raw text: asking for
        // the value is how a caller gets the safe, trimmed form.
        $this->creatures->updateBio($creature->id, $editorUserId, $guarded->value());

        return BioResult::saved('Bio saved.');
    }
}
