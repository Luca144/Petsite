<?php
/**
 * The two mood bars: how happy a creature is, and how rested.
 *
 * @package Felkyo\Templates
 *
 * Insert it with:
 *   $this->insert('partials/mood-bars', ['mood' => $mood, 'justPetted' => false]);
 * where $mood is a Felkyo\Creatures\Mood.
 *
 * EVERY BAR SAYS ITS VALUE IN WORDS AS WELL AS IN WIDTH. A coloured bar is the
 * fastest thing on the page to read and the one that tells a screen reader
 * nothing at all, so the number is written beside it and the meaning is written
 * above it (the mood sentence). Three ways of saying the same thing, which is
 * what it costs for it to work for everybody.
 *
 * role="img" with an aria-label, rather than a <progress> element: a progress bar
 * announces itself as something LOADING, which is exactly the wrong idea. This is
 * a reading, not a wait.
 */

$justPetted = $justPetted ?? false;
?>
<div class="mood-bars">
    <div class="mood-bar">
        <span class="mood-bar__label">happiness</span>
        <span class="mood-bar__track" role="img"
              aria-label="Happiness: <?= $this->e((string) $mood->happiness) ?> out of 100, <?= $this->e($mood->word) ?>">
            <?php /* The width is the only inline style on the site, and it has to
                     be: it is data, not design. Every colour still comes from the
                     stylesheet. The bar animates to its new width, so a pet is
                     something you SEE happen rather than something you notice
                     afterwards — the whole reward of the interaction. */ ?>
            <span class="mood-bar__fill mood-bar__fill--happiness<?= $justPetted ? ' mood-bar__fill--just-changed' : '' ?>"
                  style="width: <?= $this->e((string) $mood->happiness) ?>%"></span>
        </span>
        <span class="mood-bar__value" aria-hidden="true"><?= $this->e((string) $mood->happiness) ?></span>
    </div>

    <div class="mood-bar">
        <span class="mood-bar__label">energy</span>
        <span class="mood-bar__track" role="img"
              aria-label="Energy: <?= $this->e((string) $mood->energy) ?> out of 100<?= $mood->isResting ? ', resting' : '' ?>">
            <span class="mood-bar__fill mood-bar__fill--energy"
                  style="width: <?= $this->e((string) $mood->energy) ?>%"></span>
        </span>
        <span class="mood-bar__value" aria-hidden="true"><?= $this->e((string) $mood->energy) ?></span>
    </div>
</div>
