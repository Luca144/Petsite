<?php
/**
 * The panel's postcard rack: every design, and a way to start a new one.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminPostcardsController::index. Each design is shown as the
 * card itself (the same partial the post office uses), so what the artist
 * sees here is exactly what players will see.
 *
 * Variable: $designs (list of designs, drafts included).
 */
$this->layout('layout', ['title' => 'Postcards — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Postcards</h2>
        <p>
            The cards on sale at the <a href="/post-office">post office</a>. Each is a picture (uploaded as
            &ldquo;card art&rdquo;) with a few words the site prints on top &mdash; leave a calm area in the
            picture for them. Keep the words warm and never personal: they're the only thing one player can
            say to another.
        </p>
        <p><a href="/admin">&larr; back to the panel</a></p>
    </header>

    <div class="card">
        <h3>Start a new card</h3>
        <form method="post" action="/admin/postcards/create">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="card-name">Its name (shown under it on the rack)</label>
                <input class="field__input" type="text" id="card-name" name="name" maxlength="60" required>
            </div>
            <button class="btn btn--primary" type="submit">Start it as a draft</button>
        </form>
    </div>

    <ul class="postcard-rack admin-postcards">
        <?php foreach ($designs as $design): ?>
            <li>
                <a class="admin-postcards__link" href="/admin/postcards/<?= $this->e((string) $design['id']) ?>">
                    <?= $this->insert('partials/postcard', ['card' => $design]) ?>
                    <span class="postcard-tile__price">
                        <span class="admin-species__status admin-species__status--<?= $design['status'] === 'published' ? 'published' : 'draft' ?>">
                            <?= $design['status'] === 'published' ? 'on sale' : 'draft' ?>
                        </span>
                        <?= $this->e($design['name']) ?> &middot; <?= $this->e((string) $design['gem_price']) ?> gems
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
