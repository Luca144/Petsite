<?php
/**
 * The Pictures screen — the art store: upload, browse, delete.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminPicturesController for artists and owners. The upload
 * form comes first (it is what the artist came for), the grid below. Each
 * card is one picture at a glance: the picture itself, its label, kind,
 * size, and whether it animates — as text, never colour alone.
 *
 * Variables: $pictures (arrays of file => ArtFile, canDelete => bool),
 * $kinds (ArtKind[]), $totalBytes (int), $diskWarning (bool),
 * $maxMegabytes (int), $maxDimension (int).
 */
$this->layout('layout', ['title' => 'Pictures — Felkyo Creatures']);

/* Bytes → a human size, for the cards and the total. */
$humanSize = static function (int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }

    return number_format(max(1, intdiv($bytes, 1024))) . ' KB';
};
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Pictures</h2>
        <p>
            Every picture the site can show lives here. Upload sprites, icons,
            portraits, card art, backgrounds and library art; the creature
            pages will pick from this shelf.
            <span class="admin-pictures__total">
                Holding <?= $this->e($humanSize($totalBytes)) ?> in
                <?= count($pictures) ?> picture<?= count($pictures) === 1 ? '' : 's' ?>.
            </span>
        </p>
        <?php if ($diskWarning): ?>
            <p class="admin-pictures__warning" role="status">
                The art disk is getting full. Ask a coder or owner to make
                room before it runs out.
            </p>
        <?php endif; ?>
        <?php if (!empty($serverLimitMegabytes)): ?>
            <?php /* The server refuses big files before the site sees them —
                     so say it here, not as a baffling "too big" later. The
                     fix lives in php.ini at the project root. */ ?>
            <p class="admin-pictures__warning" role="status">
                This server only lets <?= $this->e((string) $serverLimitMegabytes) ?> MB through per
                upload, less than the site&rsquo;s <?= $this->e((string) $maxMegabytes) ?> MB.
                A coder needs to raise <code>upload_max_filesize</code> in the
                project&rsquo;s php.ini.
            </p>
        <?php endif; ?>
    </header>

    <div class="card admin-pictures__upload">
        <h3>Add a picture</h3>
        <?php /* enctype is what lets a form carry a file at all. Without it the
                 browser sends the filename as text and no bytes. */ ?>
        <form method="post" action="/admin/pictures/upload" enctype="multipart/form-data">
            <?= $this->csrf_field() ?>

            <div class="field">
                <label class="field__label" for="picture">The file</label>
                <input class="field__input admin-pictures__file" type="file" id="picture" name="picture"
                       accept=".gif,.png,.webp,image/gif,image/png,image/webp" required>
                <p class="field__hint">
                    GIF, PNG or WebP, up to <?= $this->e((string) $maxMegabytes) ?> MB and
                    <?= $this->e((string) $maxDimension) ?>×<?= $this->e((string) $maxDimension) ?> pixels.
                    Animated files are kept exactly as you exported them.
                </p>
            </div>

            <fieldset class="admin-roles__choices">
                <legend class="field__label">What kind of picture is it?</legend>
                <?php foreach ($kinds as $kind): ?>
                    <?php /* Radio cards, never a dropdown (locked UI rule). */ ?>
                    <label class="admin-roles__choice">
                        <input type="radio" name="kind" value="<?= $this->e($kind->value) ?>" required>
                        <span class="admin-roles__choice-name"><?= $this->e($kind->label()) ?></span>
                        <span class="admin-roles__choice-sentence"><?= $this->e($kind->description()) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <button class="btn btn--primary" type="submit">Upload it</button>
        </form>
    </div>

    <?php if (empty($pictures)): ?>
        <div class="card">
            <p>Nothing uploaded yet. The first picture goes in above.</p>
        </div>
    <?php else: ?>
        <ul class="admin-pictures__grid">
            <?php foreach ($pictures as $entry): ?>
                <?php $picture = $entry['file']; ?>
                <li class="admin-pictures__card card">
                    <?php /* The picture at whole-number scale so pixel art stays
                             crisp; the frame is fixed, the image sits inside it.
                             Alt text is the artist's own label for it. */ ?>
                    <div class="admin-pictures__frame">
                        <img class="admin-pictures__image"
                             src="<?= $this->e($picture->url()) ?>"
                             alt="<?= $this->e($picture->originalName) ?>"
                             width="<?= $this->e((string) $picture->width) ?>"
                             height="<?= $this->e((string) $picture->height) ?>"
                             loading="lazy">
                    </div>
                    <p class="admin-pictures__name"><?= $this->e($picture->originalName) ?></p>
                    <p class="admin-pictures__meta">
                        <?= $this->e($picture->kind->label()) ?> &middot;
                        <?= $this->e((string) $picture->width) ?>×<?= $this->e((string) $picture->height) ?> &middot;
                        <?= $this->e($humanSize($picture->bytes)) ?>
                        <?php if ($picture->isAnimated): ?>
                            &middot; <span class="admin-pictures__animated">animated</span>
                        <?php endif; ?>
                    </p>
                    <p class="admin-pictures__meta">
                        by <?= $this->e($picture->uploaderName ?? 'an account now gone') ?>
                    </p>
                    <?php if ($entry['canDelete']): ?>
                        <?php /* Destructive, so it confirms (golden rule 9) — a
                                 native confirm(), which works without any JS
                                 framework and degrades to "just delete" only if
                                 JavaScript is off, which staff browsers have on. */ ?>
                        <form method="post" action="/admin/pictures/<?= $this->e((string) $picture->id) ?>/delete"
                              class="admin-pictures__delete"
                              onsubmit="return confirm('Delete <?= $this->e(addslashes($picture->originalName)) ?>? This cannot be undone.');">
                            <?= $this->csrf_field() ?>
                            <button type="submit" class="btn btn--quiet">delete</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
