<?php
/**
 * One item a player owns: what it is, and what they can do with it.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ItemController::show(). Variables: $stack (OwnedItemStack),
 * $flash (string|null).
 */
$item = $stack->item;
$category = $item->category;

// Same three-source cascade as the card (M2.4): the linked art-store icon,
// else the old convention file, else the category's icon stands in.
$conventionFile = dirname(__DIR__, 2) . '/public' . $item->conventionPath();
$iconSrc = $item->hasStoredIcon()
    ? $item->iconUrl()
    : (is_file($conventionFile) ? $item->conventionPath() : null);
$hasArtwork = $iconSrc !== null;

$this->layout('layout', ['title' => $item->name . ' — Felkyo Creatures']);
?>

<?= $this->insert('partials/item-icons') ?>

<p class="item-detail__back"><a href="/inventory"><?= $this->t('item.back-to-your-things', '← Back to your things') ?></a></p>

<article class="item-detail" style="--card-tint: <?= $this->e($category->colourVariable()) ?>">

    <div class="item-detail__art">
        <?php if ($hasArtwork): ?>
            <img class="pixelated" src="<?= $this->e($iconSrc) ?>"
                 alt="<?= $this->e($item->name) ?>" width="128" height="128">
        <?php else: ?>
            <svg class="item-detail__placeholder" aria-hidden="true" focusable="false">
                <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
            </svg>
        <?php endif; ?>
    </div>

    <h2 class="item-detail__name"><?= $this->e($item->name) ?></h2>
    <p class="library-back"><a href="/library/item/<?= $this->e((string) $item->id) ?>"><?= $this->t('item.where-does-this-come-from', 'where does this come from?') ?></a></p>

    <p class="item-detail__category">
        <svg class="category-icon" aria-hidden="true" focusable="false">
            <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
        </svg>
        <?= $this->t('item.in-your-things', '{category_name} · {quantity} in your things', ['category_name' => $category->name, 'quantity' => (string) $stack->quantity]) ?>
    </p>

    <?php if ($item->description !== null && $item->description !== ''): ?>
        <p class="item-detail__desc"><?= $this->e($item->description) ?></p>
    <?php endif; ?>
</article>

<div class="item-actions">
    <?php if ($stack->isSellable()): ?>
        <form method="post" action="/inventory/<?= $this->e((string) $item->id) ?>/sell">
            <?= $this->csrf_field() ?>
            <button class="btn btn--primary" type="submit">
                <?= $this->t('item.sell-one-for', 'Sell one for {sell_value} {coins}', ['sell_value' => (string) $item->sellValue, 'coins' => $currencyName ?? 'coins']) ?>
            </button>
        </form>
    <?php else: ?>
        <?php /* Never a dead end (golden rule 3): say why there is no sell button
                 and what can be done instead, rather than quietly showing nothing
                 and leaving somebody wondering whether the page is broken. */ ?>
        <p class="item-actions__note">
            <?= $this->t('item.no-shop-around-here-would', 'No shop around here would buy a {item_name}. You can keep it, or throw it away.', ['item_name' => $item->name]) ?>
        </p>
    <?php endif; ?>

    <?php /* Throwing something away cannot be undone, so it asks first (golden
             rule 9). This is a plain <details> panel rather than a pop-up: it
             needs no JavaScript, it works by keyboard, and a screen reader
             announces it as an expandable section. Nothing is destroyed by one
             stray tap. */ ?>
    <details class="item-discard">
        <summary class="item-discard__summary"><?= $this->t('item.throw-one-away', 'Throw one away') ?></summary>
        <div class="item-discard__panel">
            <p>
                <?= $this->t('item.this-gets-rid-of-one', 'This gets rid of one {item_name} for nothing, and it can’t be undone.', ['item_name' => $item->name]) ?>
            </p>
            <form method="post" action="/inventory/<?= $this->e((string) $item->id) ?>/discard">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit">
                    <?= $this->t('item.yes-throw-one-away', 'Yes, throw one away') ?>
                </button>
            </form>
        </div>
    </details>
</div>
