<?php
/**
 * Preview: this species on the real creature layout, before publishing.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminCreaturePreviewController. The sheet below is the SAME
 * partial the live creature page renders — not a copy — so what the artist
 * sees here is what players will get, at any width this page is opened at.
 *
 * Variables: $species (Species), $creature (the stand-in), $stage (string),
 * $stageNames (string[]), $level (int), $mood (Mood).
 */
$this->layout('layout', ['title' => 'Preview: ' . $this->e($species->name) . ' — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Previewing <?= $this->e($species->name) ?></h2>
        <p>
            This is the real creature page, with a stand-in
            <?= $species->isPublished() ? '' : '&mdash; players cannot see any of it until you publish' ?>.
            Open this address on your phone to check the small screen.
        </p>
        <?php /* The stage switcher: toggle links, the current one marked in
                 words and by aria-current. */ ?>
        <nav class="admin-preview__stages" aria-label="Preview stage">
            <?php foreach ($stageNames as $stageName): ?>
                <a class="btn <?= $stageName === $stage ? 'btn--primary' : 'btn--secondary' ?>"
                   <?= $stageName === $stage ? 'aria-current="true"' : '' ?>
                   href="/admin/creatures/<?= $this->e((string) $species->id) ?>/preview?stage=<?= $this->e(rawurlencode($stageName)) ?>">
                    <?= $this->e($stageName) ?><?= $stageName === $stage ? ' (showing)' : '' ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <p><a href="/admin/creatures/<?= $this->e((string) $species->id) ?>">&larr; back to the editor</a></p>
    </header>

    <?php /* The line between panel and page: everything below is the sheet
             players get, byte for byte the same partial. */ ?>
    <?= $this->insert('partials/creature-sheet', [
        'creature' => $creature,
        'species' => $species,
        'stage' => $stage,
        'level' => $level,
        'timesPetted' => 0,
        'mood' => $mood,
        'owner' => null,
        'canPet' => false,
        'isOwner' => false,
        'treats' => [],
        'previewMode' => true,
    ]) ?>
</section>
