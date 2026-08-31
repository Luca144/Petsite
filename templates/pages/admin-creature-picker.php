<?php
/**
 * Choosing a picture for one slot of one species.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminCreaturePickerController. Every picture of the right
 * kind, as a grid of big tappable buttons — each one a tiny form, because a
 * choice is a POST and this site does not do JavaScript-only interactions.
 *
 * Variables: $species (Species), $slot (string — a stage name or 'library'),
 * $wantedKind (ArtKind), $choices (ArtFile[]), $currentArtId (?int).
 */
$this->layout('layout', ['title' => 'Choose a picture — Felkyo Creatures']);

$slotLabel = $slot === 'library' ? 'library picture' : $slot . ' stage';
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>A picture for <?= $this->e($species->name) ?>&rsquo;s <?= $this->e($slotLabel) ?></h2>
        <p>
            Showing everything in the art store filed as
            <strong><?= $this->e($wantedKind->label()) ?></strong>. Not here?
            <a href="/admin/pictures">Upload it there first</a> — under that kind —
            and it will appear on this shelf.
        </p>
        <p><a href="/admin/creatures/<?= $this->e((string) $species->id) ?>">&larr; back without choosing</a></p>
    </header>

    <?php if ($currentArtId !== null): ?>
        <div class="card">
            <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/picture/<?= $this->e(rawurlencode($slot)) ?>">
                <?= $this->csrf_field() ?>
                <input type="hidden" name="art_id" value="0">
                <button class="btn btn--secondary" type="submit">Unlink the current picture</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (empty($choices)): ?>
        <div class="card">
            <p>
                Nothing on this shelf yet. <a href="/admin/pictures">Upload a
                picture</a> as &ldquo;<?= $this->e($wantedKind->label()) ?>&rdquo; and come back —
                it will be waiting here.
            </p>
        </div>
    <?php else: ?>
        <ul class="admin-picker__grid">
            <?php foreach ($choices as $choice): ?>
                <li>
                    <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/picture/<?= $this->e(rawurlencode($slot)) ?>">
                        <?= $this->csrf_field() ?>
                        <input type="hidden" name="art_id" value="<?= $this->e((string) $choice->id) ?>">
                        <?php /* The button IS the picture — one tap chooses. The
                                 current choice says so in words too. */ ?>
                        <button type="submit"
                                class="admin-picker__choice card<?= $choice->id === $currentArtId ? ' admin-picker__choice--current' : '' ?>">
                            <span class="admin-picker__frame">
                                <img class="pixelated" src="<?= $this->e($choice->url()) ?>"
                                     alt="<?= $this->e($choice->originalName) ?>" loading="lazy">
                            </span>
                            <span class="admin-picker__name"><?= $this->e($choice->originalName) ?></span>
                            <?php if ($choice->id === $currentArtId): ?>
                                <span class="admin-picker__current-word">currently chosen</span>
                            <?php endif; ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
