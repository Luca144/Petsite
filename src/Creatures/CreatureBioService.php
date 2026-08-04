<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The rules for writing a creature's bio.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: validates a bio (length and blocked words) and saves it. Who is
 * ALLOWED to edit (only the owner) is checked by the controller before calling
 * this; this class is about "is the text acceptable, and store it".
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
     * Validate and save a new bio for a creature. Returns a BioResult saying
     * whether it was saved, with a message to show either way.
     */
    public function updateBio(Creature $creature, string $bio): BioResult
    {
        $bio = trim($bio);

        if (mb_strlen($bio) > $this->maxLength) {
            return BioResult::rejected("The bio must be no more than {$this->maxLength} characters.");
        }

        if ($this->filter->containsBlockedWord($bio)) {
            return BioResult::rejected('Please keep the bio friendly — some words in it are not allowed.');
        }

        $this->creatures->updateBio($creature->id, $bio);

        return BioResult::saved('Bio saved.');
    }
}
