<?php
/**
 * A creature moment — one of your creatures pops up in a speech bubble.
 *
 * @package Felkyo\Templates
 *
 * Inserted by layout.php at the top of the page content, only when the roll in
 * CreatureMoments produced a moment (most pages it does not — the rarity is the
 * point; see that class for the reasoning).
 *
 * $moment is ['summary' => (from CreatureProfileBuilder), 'line' => string].
 *
 * THE WHOLE BUBBLE IS ONE LINK to the creature's page: a moment is an
 * invitation to visit, and one big target is kinder to tap than a small name
 * link inside a decoration. The portrait's alt is empty on purpose — the
 * creature's name is already in the spoken line, and a screen reader should
 * hear the sentence once, not the name twice.
 */
$creature = $moment['summary']['creature'];
$slug = $moment['summary']['species']?->slug ?? '';
// Same image convention as creature-card.php: slug + current growth stage.
$image = '/assets/creatures/' . rawurlencode($slug) . '/' . rawurlencode($moment['summary']['stage']) . '.gif';
?>
<a class="creature-moment" href="/creature/<?= $this->e((string) $creature->id) ?>">
    <img class="creature-moment__img pixelated"
         src="<?= $this->e($image) ?>"
         alt="" width="64" height="64">
    <span class="creature-moment__bubble">
        <?= $this->e($moment['line']) ?>
        <span class="visually-hidden">&mdash; visit <?= $this->e($creature->name) ?></span>
    </span>
</a>
