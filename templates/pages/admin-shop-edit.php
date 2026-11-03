<?php
/**
 * One shop, everything about it, edited in place.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminShopEditController (Artist role). The one-sitting shop
 * editor: words, keeper, the two pictures, and the shelves (their own
 * partial — the longest section). The general store links to its real
 * player-facing page, which IS the preview.
 *
 * Variables: $shop (Shop), $listings (arrays), $stockableItems (Item[]),
 * $versions (arrays).
 */
$this->layout('layout', ['title' => $this->e($shop->name) . ' — Shops — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($shop->name) ?></h2>
        <p class="admin-species__statusline">
            <span class="admin-species__status admin-species__status--<?= $shop->isPublished() ? 'published' : 'draft' ?>">
                <?= $shop->isPublished() ? 'published' : 'draft' ?>
            </span>
        </p>

        <div class="admin-species__actions">
            <?php if ($shop->slug === 'general-store'): ?>
                <?php /* The one shop players can reach today; its live page
                         is the honest preview. Other shops get doors in M9. */ ?>
                <a class="btn btn--secondary" href="/shop">See the real shop page</a>
            <?php endif; ?>
            <?php if ($shop->isPublished()): ?>
                <form method="post" action="/admin/shops/<?= $this->e((string) $shop->id) ?>/unpublish">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--secondary" type="submit">Back to draft</button>
                </form>
            <?php else: ?>
                <form method="post" action="/admin/shops/<?= $this->e((string) $shop->id) ?>/publish">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Publish</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <div class="card">
        <h3>Names &amp; words</h3>
        <form method="post" action="/admin/shops/<?= $this->e((string) $shop->id) ?>/words">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Name</label>
                <input class="field__input" type="text" id="name" name="name"
                       value="<?= $this->e($shop->name) ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="description">Description (under the name on the shop page)</label>
                <textarea class="field__input" id="description" name="description" rows="2"><?= $this->e((string) $shop->description) ?></textarea>
            </div>
            <button class="btn btn--primary" type="submit">Save the words</button>
        </form>
    </div>

    <div class="card">
        <h3>The keeper</h3>
        <form method="post" action="/admin/shops/<?= $this->e((string) $shop->id) ?>/keeper">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="keeper_name">Their name</label>
                <input class="field__input" type="text" id="keeper_name" name="keeper_name"
                       value="<?= $this->e((string) $shop->keeperName) ?>">
            </div>
            <div class="field">
                <label class="field__label" for="keeper_greeting">Their greeting (spoken under the shop&rsquo;s description)</label>
                <textarea class="field__input" id="keeper_greeting" name="keeper_greeting" rows="2"><?= $this->e((string) $shop->keeperGreeting) ?></textarea>
            </div>
            <div class="field">
                <label class="field__label" for="keeper_refusal">What they say when a search finds nothing (the shop only shows a search box once it has lots of items, so small shops never need this)</label>
                <input class="field__input" type="text" id="keeper_refusal" name="keeper_refusal"
                       value="<?= $this->e((string) $shop->keeperRefusal) ?>">
            </div>
            <button class="btn btn--primary" type="submit">Save the keeper</button>
        </form>
    </div>

    <div class="card admin-stage">
        <h3>Pictures</h3>
        <div class="admin-stage__row">
            <span class="admin-stage__art">
                <?php if ($shop->portraitUrl() !== null): ?>
                    <img class="pixelated" src="<?= $this->e($shop->portraitUrl()) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="admin-stage__none">no portrait yet</span>
                <?php endif; ?>
            </span>
            <a class="btn btn--secondary" href="/admin/shops/<?= $this->e((string) $shop->id) ?>/picture/portrait">
                Choose the keeper&rsquo;s portrait
            </a>
        </div>
        <div class="admin-stage__row">
            <span class="admin-stage__art">
                <?php if ($shop->backgroundUrl() !== null): ?>
                    <img src="<?= $this->e($shop->backgroundUrl()) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="admin-stage__none">no background yet</span>
                <?php endif; ?>
            </span>
            <a class="btn btn--secondary" href="/admin/shops/<?= $this->e((string) $shop->id) ?>/picture/background">
                Choose the background
            </a>
        </div>
    </div>

    <?= $this->insert('partials/admin-shop-shelves', [
        'shop' => $shop,
        'listings' => $listings,
        'stockableItems' => $stockableItems,
    ]) ?>

    <div class="card">
        <h3>Versions</h3>
        <p class="field__hint">Every save is kept (newest 20). Restoring brings the shelves back too — and never touches whether the shop is published.</p>
        <?php if (empty($versions)): ?>
            <p>No history yet — it starts with the first save.</p>
        <?php else: ?>
            <ul class="admin-species__versions">
                <?php foreach ($versions as $version): ?>
                    <li class="admin-species__version">
                        <span class="admin-species__version-when"><?= $this->e($version['created_at']) ?></span>
                        <span>by <?= $this->e($version['saved_by'] ?? 'the site') ?></span>
                        <span>as &ldquo;<?= $this->e((string) ($version['snapshot']['name'] ?? '?')) ?>&rdquo;</span>
                        <form method="post" action="/admin/shops/<?= $this->e((string) $shop->id) ?>/revert">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="version_id" value="<?= $this->e((string) $version['id']) ?>">
                            <button type="submit" class="btn--quiet">restore this</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
