<?php
/**
 * The shop list, and the door to a new shop.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminShopsController (Artist role). One shop today; a shop
 * built here is complete data the day M9 gives it a door of its own.
 *
 * Variables: $shops (Shop[]).
 */
$this->layout('layout', ['title' => 'Shops — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Shops</h2>
        <p>
            Each shop in one sitting: its keeper and their lines, its look,
            and what is on the shelves when. More shops get their own doors
            in a later milestone — built here, they are ready and waiting.
        </p>
    </header>

    <div class="card admin-species__new">
        <h3>Start a new shop</h3>
        <form method="post" action="/admin/shops/create" class="admin-species__new-form">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Its name</label>
                <input class="field__input" type="text" id="name" name="name" required
                       placeholder="e.g. The Moonlit Apothecary">
            </div>
            <button class="btn btn--primary" type="submit">Start it (as a draft)</button>
        </form>
    </div>

    <ul class="admin-species__grid">
        <?php foreach ($shops as $shop): ?>
            <li>
                <a class="admin-species__card card" href="/admin/shops/<?= $this->e((string) $shop->id) ?>">
                    <span class="admin-species__face">
                        <?php if ($shop->portraitUrl() !== null): ?>
                            <img class="pixelated" src="<?= $this->e($shop->portraitUrl()) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="admin-stage__none">no keeper art</span>
                        <?php endif; ?>
                    </span>
                    <span class="admin-species__name">
                        <?= $this->e($shop->name) ?>
                        <?php if ($shop->keeperName !== null): ?>
                            <span class="admin-audit__subject">kept by <?= $this->e($shop->keeperName) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="admin-species__status admin-species__status--<?= $shop->isPublished() ? 'published' : 'draft' ?>">
                        <?= $shop->isPublished() ? 'published' : 'draft' ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
