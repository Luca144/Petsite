<?php

declare(strict_types=1);

namespace Felkyo\Library;

/**
 * Decides whether a newly arriving creature is born in an alternate form.
 *
 * @package Felkyo\Library
 *
 * Every way a creature arrives (the starter, the shop, a rare find while
 * exploring) asks this once. Most of the time the answer is "the default
 * form" (null); with a small chance — gameplay.forms.birth_chance_percent in
 * config — it is one of the species' PUBLISHED forms, picked evenly. That
 * makes a form a lucky surprise to hope for, which is what the library is
 * for: seeing what's out there.
 *
 * A species with no published forms always gets null, whatever the chance.
 */
final class CreatureFormRoller
{
    /**
     * @param int $chancePercent 0–100; 0 switches forms at birth off.
     */
    public function __construct(
        private SpeciesFormRepository $forms,
        private int $chancePercent,
    ) {
    }

    /** A form id for a new creature of this species, or null for the default. */
    public function rollFor(int $speciesId): ?int
    {
        if ($this->chancePercent <= 0 || random_int(1, 100) > $this->chancePercent) {
            return null;
        }

        $formIds = $this->forms->publishedIdsFor($speciesId);
        if ($formIds === []) {
            return null;
        }

        return $formIds[random_int(0, count($formIds) - 1)];
    }
}
