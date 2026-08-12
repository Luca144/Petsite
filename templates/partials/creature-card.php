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
<a class="creature-card<?= $creature->isFeatured() ? ' creature-card--featured' : '' ?>"
   href="/creature/<?= $this->e((string) $creature->id) ?>">

    <?php if ($creature->isFeatured()): ?>
        <?php /* Choosing a creature to show off used to change nothing except the
                 order they appeared in, which is not something anybody notices.
                 A favourite now looks like one: a gold star, a warmer card, and the
                 word "favourite" in text — never the colour alone, and never only
                 the star, because an icon on its own has to be learnt. */ ?>
        <span class="creature-card__favourite">
            <span aria-hidden="true">&#9733;</span>
            <span class="creature-card__favourite-word">favourite</span>
        </span>
    <?php endif; ?>

    <img class="creature-card__img pixelated"
         src="<?= $this->e($image) ?>"
         alt="<?= $this->e($creature->name) ?>"
         width="96" height="96">
    <span class="creature-card__name"><?= $this->e($creature->name) ?></span>
    <span class="creature-card__meta">
        <?= $this->e($species?->name ?? 'Unknown') ?> &middot; lvl <?= $this->e((string) $summary['level']) ?>
    </span>
</a>
