<?php
/**
 * Editing one postcard design: a live-looking preview, its picture, its
 * words and price, and putting it on (or off) the rack.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminPostcardsController::show.
 * Variables: $design (array), $timesSent (int).
 */
$this->layout('layout', ['title' => $design['name'] . ' — Postcards — Felkyo Creatures']);
$id = (string) $design['id'];
$onSale = $design['status'] === 'published';
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($design['name']) ?></h2>
        <p class="admin-species__statusline">
            <span class="admin-species__status admin-species__status--<?= $onSale ? 'published' : 'draft' ?>">
                <?= $onSale ? 'on sale' : 'draft' ?>
            </span>
            <?php if ($timesSent > 0): ?>
                <span class="field__hint">sent <?= $this->e((string) $timesSent) ?> times</span>
            <?php endif; ?>
        </p>
        <div class="admin-species__actions">
            <form method="post" action="/admin/postcards/<?= $this->e($id) ?>/<?= $onSale ? 'unpublish' : 'publish' ?>">
                <?= $this->csrf_field() ?>
                <button class="btn <?= $onSale ? 'btn--secondary' : 'btn--primary' ?>" type="submit">
                    <?= $onSale ? 'Take it off the rack' : 'Put it on sale' ?>
                </button>
            </form>
        </div>
        <p><a href="/admin/postcards">&larr; all cards</a></p>
    </header>

    <div class="card admin-postcard-preview">
        <h3>How it looks</h3>
        <div class="admin-postcard-preview__card">
            <?= $this->insert('partials/postcard', ['card' => $design]) ?>
        </div>
        <a class="btn btn--secondary" href="/admin/postcards/<?= $this->e($id) ?>/picture">
            <?= $design['art_name'] === null ? 'Choose a picture' : 'Change the picture' ?>
        </a>
    </div>

    <form class="card" method="post" action="/admin/postcards/<?= $this->e($id) ?>/save">
        <?= $this->csrf_field() ?>
        <div class="field">
            <label class="field__label" for="name">Name</label>
            <input class="field__input" type="text" id="name" name="name" maxlength="60" value="<?= $this->e($design['name']) ?>" required>
        </div>
        <div class="field">
            <label class="field__label" for="message">The words on the card (up to 200 characters)</label>
            <textarea class="field__input" id="message" name="message" rows="2" maxlength="200" required><?= $this->e($design['message']) ?></textarea>
        </div>
        <div class="field">
            <label class="field__label" for="gem_price">Price in gems</label>
            <input class="field__input" type="number" id="gem_price" name="gem_price" min="1" max="500" value="<?= $this->e((string) $design['gem_price']) ?>" required>
        </div>
        <button class="btn btn--primary" type="submit">Save</button>
    </form>

    <?php if ($timesSent === 0): ?>
        <div class="card">
            <form method="post" action="/admin/postcards/<?= $this->e($id) ?>/delete"
                  onsubmit="return confirm('Delete this card design?');">
                <?= $this->csrf_field() ?>
                <button class="btn--quiet" type="submit">delete this card</button>
            </form>
        </div>
    <?php endif; ?>
</section>
