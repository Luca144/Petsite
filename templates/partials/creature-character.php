<?php
/**
 * Who a creature is (M3.2): its level title, friendly age, whether it is
 * kept at its stage, and its traits with how it came by each.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/creature.php under the creature sheet.
 *
 * Variables: $creature (Creature), $stage (string), $character (array from
 * CharacterSheet::for(), or null when not wired — then nothing shows).
 */
if (empty($character)) {
    return;
}
?>
<section class="creature-character" aria-labelledby="creature-character">
    <h2 id="creature-character" class="panel-label">Who <?= $this->e($creature->name) ?> is</h2>

    <p>
        <?php if ($character['levelTitle'] !== null): ?>
            <span class="creature-chip creature-chip--title"><?= $this->e($character['levelTitle']) ?></span>
        <?php endif; ?>
        <?php if ($character['ageLabel'] !== null): ?>
            <span class="creature-chip creature-chip--title"><?= $this->e($character['ageLabel']) ?></span>
        <?php endif; ?>
        <?php if ($creature->isFrozen): ?>
            <span class="creature-chip"><span aria-hidden="true">&#10052;</span> kept as a <?= $this->e($stage) ?></span>
        <?php elseif ($creature->shownStage !== null): ?>
            <span class="creature-chip"><span aria-hidden="true">&#8599;</span> catching up on growing</span>
        <?php endif; ?>
        <?php if ($creature->isAtPound()): ?>
            <span class="creature-chip">waiting at <a href="/pound">the pound</a></span>
        <?php endif; ?>
    </p>

    <?php if ($character['newTraits'] !== []): ?>
        <p class="flash" role="status">
            Something new about <?= $this->e($creature->name) ?>: <?= $this->e(strtolower(implode(', ', $character['newTraits']))) ?>!
        </p>
    <?php endif; ?>

    <?php if ($character['traits'] === []): ?>
        <p class="empty-state">No traits yet &mdash; they come with time and care.</p>
    <?php else: ?>
        <ul class="trait-list">
            <?php foreach ($character['traits'] as $trait): ?>
                <li class="trait trait--<?= $this->e($trait->tier) ?>">
                    <span class="trait__name"><?= $this->e($trait->name) ?></span>
                    <p class="trait__description"><?= $this->e($trait->description) ?></p>
                    <span class="trait__how"><?= $this->e((string) $trait->how) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
