<?php
/**
 * A single creature "card" — a small portrait with name and species/level.
 *
 * @package Felkyo\Templates
 *
 * Shared by the collection view and the browse page. Insert it with
 * $this->insert('partials/creature-card', ['summary' => $summary]).
 *
 * $summary is one entry from CreatureProfileBuilder::summariesFor():
 *   ['creature' => Creature, 'species' => ?Species, 'level' => int, 'stage' => string]
 */
$creature = $summary['creature'];
$species = $summary['species'];
$slug = $species?->slug ?? '';
// Same image convention as the creature page: slug + current stage.
$image = '/assets/creatures/' . rawurlencode($slug) . '/' . rawurlencode($summary['stage']) . '.gif';
?>
<a class="creature-card" href="/creature/<?= $this->e((string) $creature->id) ?>">
    <img class="creature-card__img pixelated"
         src="<?= $this->e($image) ?>"
         alt="<?= $this->e($creature->name) ?>"
         width="96" height="96">
    <span class="creature-card__name"><?= $this->e($creature->name) ?></span>
    <span class="creature-card__meta">
        <?= $this->e($species?->name ?? 'Unknown') ?> &middot; lvl <?= $this->e((string) $summary['level']) ?>
    </span>
</a>
