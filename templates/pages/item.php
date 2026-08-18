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

// Same convention as the card: real artwork if it exists, the category's icon
// as a stand-in until it does.
$artworkFile = dirname(__DIR__, 2) . '/public' . $item->imagePath();
$hasArtwork = is_file($artworkFile);

$this->layout('layout', ['title' => $item->name . ' — Felkyo Creatures']);
?>

<?= $this->insert('partials/item-icons') ?>

<p class="item-detail__back"><a href="/inventory">&larr; Back to your things</a></p>

<article class="item-detail" style="--card-tint: <?= $this->e($category->colourVariable()) ?>">

    <div class="item-detail__art">
        <?php if ($hasArtwork): ?>
            <img class="pixelated" src="<?= $this->e($item->imagePath()) ?>"
                 alt="<?= $this->e($item->name) ?>" width="128" height="128">
        <?php else: ?>
            <svg class="item-detail__placeholder" aria-hidden="true" focusable="false">
                <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
            </svg>
        <?php endif; ?>
    </div>

    <h2 class="item-detail__name"><?= $this->e($item->name) ?></h2>

    <p class="item-detail__category">
        <svg class="category-icon" aria-hidden="true" focusable="false">
            <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
        </svg>
        <?= $this->e($category->name) ?>
        &middot;
        <?= $this->e((string) $stack->quantity) ?> in your things
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
                Sell one for <?= $this->e((string) $item->sellValue) ?> <?= $this->e($currencyName ?? 'coins') ?>
            </button>
        </form>
    <?php else: ?>
        <?php /* Never a dead end (golden rule 3): say why there is no sell button
                 and what can be done instead, rather than quietly showing nothing
                 and leaving somebody wondering whether the page is broken. */ ?>
        <p class="item-actions__note">
            No shop around here would buy a <?= $this->e($item->name) ?>.
            You can keep it, or throw it away.
        </p>
    <?php endif; ?>

    <?php /* Throwing something away cannot be undone, so it asks first (golden
             rule 9). This is a plain <details> panel rather than a pop-up: it
             needs no JavaScript, it works by keyboard, and a screen reader
             announces it as an expandable section. Nothing is destroyed by one
             stray tap. */ ?>
    <details class="item-discard">
        <summary class="item-discard__summary">Throw one away</summary>
        <div class="item-discard__panel">
            <p>
                This gets rid of one <?= $this->e($item->name) ?> for nothing,
                and it can&rsquo;t be undone.
            </p>
            <form method="post" action="/inventory/<?= $this->e((string) $item->id) ?>/discard">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit">
                    Yes, throw one away
                </button>
            </form>
        </div>
    </details>
</div>
