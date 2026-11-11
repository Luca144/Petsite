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
    <h2 id="creature-character" class="panel-label"><?= $this->t('creature-character.who-is', 'Who {name} is', ['name' => $creature->name]) ?></h2>

    <p>
        <?php if ($character['levelTitle'] !== null): ?>
            <span class="creature-chip creature-chip--title"><?= $this->e($character['levelTitle']) ?></span>
        <?php endif; ?>
        <?php if ($character['ageLabel'] !== null): ?>
            <span class="creature-chip creature-chip--title"><?= $this->e($character['ageLabel']) ?></span>
        <?php endif; ?>
        <?php if ($creature->isFrozen): ?>
            <span class="creature-chip"><span aria-hidden="true">&#10052;</span> <?= $this->t('creature-character.kept-as-a', 'kept as a {stage}', ['stage' => $stage]) ?></span>
        <?php elseif ($creature->shownStage !== null): ?>
            <span class="creature-chip"><span aria-hidden="true">&#8599;</span> <?= $this->t('creature-character.catching-up-on-growing', 'catching up on growing') ?></span>
        <?php endif; ?>
        <?php if ($creature->isAtPound()): ?>
            <span class="creature-chip"><?= $this->t('creature-character.waiting-at', 'waiting at') ?> <a href="/pound"><?= $this->t('creature-character.the-pound', 'the pound') ?></a></span>
        <?php endif; ?>
    </p>

    <?php if ($character['newTraits'] !== []): ?>
        <p class="flash" role="status">
            <?= $this->t('creature-character.something-new-about', 'Something new about {name}: {new_traits}!', ['name' => $creature->name, 'new_traits' => strtolower(implode(', ', $character['newTraits']))]) ?>
        </p>
    <?php endif; ?>

    <?= $this->insert('partials/badge-shelf', ['badges' => $character['badges'] ?? [], 'heading' => 'Keepsakes']) ?>

    <?php if ($character['traits'] === []): ?>
        <p class="empty-state"><?= $this->t('creature-character.no-traits-yet-they-come', 'No traits yet — they come with time and care.') ?></p>
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
