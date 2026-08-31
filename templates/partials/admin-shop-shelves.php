<?php
/**
 * The shop's shelves: what is listed, when it is available, and stocking.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/admin-shop-edit.php. Each listing shows its season and
 * its item's honest state — a listed item that has gone back to draft is
 * SAID to be invisible to players, not silently absent. Stocking takes an
 * item (radio tiles of published items) and an optional season; native
 * date inputs, blank means always.
 *
 * Variables: $shop (Shop), $listings (arrays from listingsFor),
 * $stockableItems (Item[] — published only).
 */
?>
<div class="card">
    <h3>The shelves</h3>

    <?php if (empty($listings)): ?>
        <p>Nothing on the shelves yet.</p>
    <?php else: ?>
        <ul class="admin-species__versions">
            <?php foreach ($listings as $listing): ?>
                <li class="admin-species__version">
                    <span><strong><?= $this->e($listing['item_name']) ?></strong></span>
                    <span class="admin-species__version-when">
                        <?php if ($listing['available_from'] === null && $listing['available_to'] === null): ?>
                            all year round
                        <?php else: ?>
                            <?= $this->e($listing['available_from'] ?? 'always') ?> &rarr; <?= $this->e($listing['available_to'] ?? 'always') ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($listing['item_status'] !== 'published'): ?>
                        <span class="admin-species__missing">the item is a draft — players can&rsquo;t see this listing</span>
                    <?php endif; ?>
                    <form method="post" action="/admin/shops/<?= $this->e((string) $shop->id) ?>/shelf/<?= $this->e((string) $listing['item_id']) ?>/remove">
                        <?= $this->csrf_field() ?>
                        <button type="submit" class="btn--quiet">take off the shelf</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h4 class="field__label">Put something on the shelf</h4>
    <?php if (empty($stockableItems)): ?>
        <p class="field__hint">No published items to shelve — publish an item first, under <a href="/admin/items">Items</a>.</p>
    <?php else: ?>
        <form method="post" action="/admin/shops/<?= $this->e((string) $shop->id) ?>/shelf">
            <?= $this->csrf_field() ?>
            <fieldset class="admin-roles__choices">
                <legend class="field__label">Which item</legend>
                <?php foreach ($stockableItems as $stockable): ?>
                    <label class="admin-roles__choice">
                        <input type="radio" name="item_id" value="<?= $this->e((string) $stockable->id) ?>" required>
                        <span class="admin-roles__choice-name"><?= $this->e($stockable->name) ?></span>
                        <span class="admin-roles__choice-sentence"><?= $this->e($stockable->category->name) ?> &middot; <?= $this->e((string) $stockable->price) ?> gems</span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <?php /* The season, optional — the 2026-08-12 decision come to
                     life: blank means always; out of season means simply not
                     offered, never "come back in March". */ ?>
            <div class="field">
                <label class="field__label" for="available_from">On the shelf from (optional)</label>
                <input class="field__input" type="date" id="available_from" name="available_from">
            </div>
            <div class="field">
                <label class="field__label" for="available_to">Until (optional)</label>
                <input class="field__input" type="date" id="available_to" name="available_to">
            </div>

            <button class="btn btn--primary" type="submit">Put it on the shelf</button>
        </form>
    <?php endif; ?>
</div>
