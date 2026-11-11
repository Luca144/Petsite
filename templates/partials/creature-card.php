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

use Felkyo\Design\PixelArt;

$creature = $summary['creature'];
$species = $summary['species'];
// THE sprite address comes from the species itself (art-store picture if one
// is linked, the old convention path if not) — one resolver instead of the
// six copies of a path this file used to be one of. See Species::spriteUrlFor.
$image = $species?->spriteUrlFor($summary['stage']) ?? '';

// The sprite's honest size: the largest WHOLE multiple of its native pixels that
// fits the frame. A fractional scale smears pixel art — see PixelArt.
[$artWidth, $artHeight] = PixelArt::displaySize($image, 104);
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
            <span class="creature-card__favourite-word"><?= $this->t('creature-card.favourite', 'favourite') ?></span>
        </span>
    <?php endif; ?>

    <?php /* The frame is a fixed box so every card keeps the same rhythm; the
             sprite inside is its own exact-multiple size, centred. */ ?>
    <span class="creature-card__art">
        <img class="creature-card__img pixelated"
             src="<?= $this->e($image) ?>"
             alt="<?= $this->e($creature->name) ?>"
             width="<?= $this->e((string) $artWidth) ?>" height="<?= $this->e((string) $artHeight) ?>">
    </span>

    <span class="creature-card__name"><?= $this->e($creature->name) ?></span>

    <?php /* Species and level on SEPARATE lines, so every card is the same height
             whether its species is "Foxlen" or something long: one line for each,
             always. On one shared line, long names wrapped and short ones did not,
             and the grid read as jitter. (The Product Owner asked for this.) */ ?>
    <span class="creature-card__meta"><?= $this->e($species?->name ?? 'Unknown') ?></span>
    <span class="creature-card__meta"><?= $this->t('creature-card.level', 'level {level}', ['level' => (string) $summary['level']]) ?></span>
</a>
