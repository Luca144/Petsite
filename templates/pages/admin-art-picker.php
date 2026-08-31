<?php
/**
 * The shared picture picker: choose one picture of one kind for one slot.
 *
 * @package Felkyo\Templates
 *
 * Used by the item editor (icons) and the shop editor (backgrounds,
 * portraits) — one template, because the page IS the same everywhere: a
 * grid of the right kind's pictures as tappable forms, an unlink button,
 * and the way to the upload shelf when the wanted picture isn't here yet.
 * (The species editor has its own older twin, pages/admin-creature-picker —
 * merging them is a tidy-up for the next quiet moment, noted in the guide.)
 *
 * Variables: $heading (string), $wantedKind (ArtKind), $choices (ArtFile[]),
 * $currentArtId (?int), $action (string — the POST target),
 * $backTo (string — the editor page).
 */
$this->layout('layout', ['title' => 'Choose a picture — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($heading) ?></h2>
        <p>
            Showing everything in the art store filed as
            <strong><?= $this->e($wantedKind->label()) ?></strong>. Not here?
            <a href="/admin/pictures">Upload it there first</a> — under that kind —
            and it will appear on this shelf.
        </p>
        <p><a href="<?= $this->e($backTo) ?>">&larr; back without choosing</a></p>
    </header>

    <?php if ($currentArtId !== null): ?>
        <div class="card">
            <form method="post" action="<?= $this->e($action) ?>">
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
                    <form method="post" action="<?= $this->e($action) ?>">
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
