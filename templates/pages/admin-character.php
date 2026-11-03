<?php
/**
 * The panel's character page (M3.2, M3.2c): the two word-ladders, and every
 * trait.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminCharacterController::show.
 *
 * Variables: $traits (CreatureTrait[]), $levelTitles and $ages
 * (array<int, string> threshold => label).
 */
$this->layout('layout', ['title' => 'Traits & titles — Felkyo Creatures']);

// Each ladder is edited as lines of "threshold: label".
$asLines = static function (array $rungs): string {
    $lines = [];
    foreach ($rungs as $threshold => $label) {
        $lines[] = $threshold . ': ' . $label;
    }

    return implode("\n", $lines);
};
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Traits &amp; titles</h2>
        <p>Who a creature is: the traits it earns or is given, the word for its level, and how its age is said.</p>
        <p><a href="/admin">&larr; back to the panel</a></p>
    </header>

    <div class="admin-ladders">
        <form class="card" method="post" action="/admin/character/ladder/level_title">
            <?= $this->csrf_field() ?>
            <h3>Level titles</h3>
            <label class="field__label" for="ladder-levels">One per line: the level it starts at, a colon, the title.</label>
            <textarea class="field__input" id="ladder-levels" name="lines" rows="9"><?= $this->e($asLines($levelTitles)) ?></textarea>
            <button class="btn btn--primary" type="submit">Save the titles</button>
        </form>
        <form class="card" method="post" action="/admin/character/ladder/age">
            <?= $this->csrf_field() ?>
            <h3>Friendly ages</h3>
            <label class="field__label" for="ladder-ages">One per line: days old, a colon, how it's said.</label>
            <textarea class="field__input" id="ladder-ages" name="lines" rows="9"><?= $this->e($asLines($ages)) ?></textarea>
            <button class="btn btn--primary" type="submit">Save the ages</button>
        </form>
    </div>

    <h3 class="library-section">Traits</h3>
    <p class="field__hint">
        Traits are flavour, plus at most one small XP bonus — they never change stats or prices.
        Earned traits arrive by themselves; pound traits come with adoption (one or two at random);
        gifts are a lucky roll on adoption.
    </p>

    <?= $this->insert('partials/admin-trait-form', ['trait' => null]) ?>

    <?php foreach ($traits as $trait): ?>
        <?= $this->insert('partials/admin-trait-form', ['trait' => $trait]) ?>
    <?php endforeach; ?>
</section>
