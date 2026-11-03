<?php
/**
 * One item's page in the library: what it is, and where to get it.
 *
 * @package Felkyo\Templates
 *
 * Rendered by LibraryController::item. Public. Answers Skerro's "where does
 * this come from?" (2026-10-13) for items, fish and plants alike — they are
 * all items, differing only by category.
 *
 * Variables: $item (Item, published), $shops (string[] shop names),
 * $areas (list of ['slug', 'name']).
 */
$this->layout('layout', ['title' => $item->name . ' — The library — Felkyo Creatures']);
$category = $item->category;
$conventionFile = dirname(__DIR__, 2) . '/public' . $item->conventionPath();
$iconSrc = $item->hasStoredIcon() ? $item->iconUrl() : (is_file($conventionFile) ? $item->conventionPath() : null);
?>

<p class="library-back"><a href="/library">&larr; the library</a></p>

<div class="library-item card" style="--card-tint: <?= $this->e($category->colourVariable()) ?>">
    <span class="library-item__art">
        <?php if ($iconSrc !== null): ?>
            <img class="pixelated" src="<?= $this->e($iconSrc) ?>" alt="" width="96" height="96">
        <?php else: ?>
            <svg class="shop-item__placeholder" aria-hidden="true" focusable="false">
                <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
            </svg>
        <?php endif; ?>
    </span>
    <div>
        <h2 class="panel-label"><?= $this->e($item->name) ?></h2>
        <p class="library-item__category"><?= $this->e($category->name) ?></p>
        <?php if ($item->description !== null && $item->description !== ''): ?>
            <p><?= $this->e($item->description) ?></p>
        <?php endif; ?>
    </div>
</div>

<h3 class="library-section">Where to find it</h3>
<?php if ($shops === [] && $areas === []): ?>
    <p class="empty-state">Nobody quite knows yet. Keep your eyes open.</p>
<?php else: ?>
    <ul class="library-sources">
        <?php foreach ($shops as $shopName): ?>
            <li>Sold at <a href="/shop"><?= $this->e($shopName) ?></a></li>
        <?php endforeach; ?>
        <?php foreach ($areas as $area): ?>
            <li>Found while exploring <a href="/explore/<?= $this->e(rawurlencode($area['slug'])) ?>"><?= $this->e($area['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?= $this->insert('partials/item-icons') ?>
