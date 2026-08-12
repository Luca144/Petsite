<?php
/**
 * The player's things, grouped by item category.
 *
 * @package Felkyo\Templates
 *
 * Rendered by InventoryController. $groups is a list of
 *   ['category' => ItemCategory, 'stacks' => OwnedItemStack[]]
 * already in the artist's chosen order, so a new kind of item appears as a new
 * section here with nothing in this file changing.
 */
$this->layout('layout', ['title' => 'Your things — Felkyo Creatures']);
?>

<?php /* The category drawings, defined once for every card on the page. */ ?>
<?= $this->insert('partials/item-icons') ?>

<h2 class="panel-label">Your things</h2>

<?php if (empty($groups)): ?>
    <p class="empty-state">
        You don&rsquo;t own anything yet — but the village store is warm and Ruinily is in.
    </p>
    <div class="button-row"><a class="btn btn--secondary" href="/shop">Visit the shop</a></div>
<?php else: ?>
    <?php foreach ($groups as $group): ?>
        <?php $category = $group['category']; ?>

        <h3 class="inventory-type">
            <?php /* The heading carries the icon too, so a section is recognisable
                     while scrolling without reading every word. */ ?>
            <svg class="item-card__icon" aria-hidden="true" focusable="false">
                <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
            </svg>
            <?= $this->e($category->name) ?>
        </h3>

        <div class="item-grid">
            <?php foreach ($group['stacks'] as $stack): ?>
                <?= $this->insert('partials/item-card', ['stack' => $stack]) ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
