<?php
/**
 * The player's things, with the finder row to narrow them.
 *
 * @package Felkyo\Templates
 *
 * Rendered by InventoryController. Variables:
 *   $groups     — ['category' => ItemCategory, 'stacks' => OwnedItemStack[]] list
 *   $stacks     — the same piles as a flat list (used when a filter is active)
 *   $isFiltered — whether a category or search is narrowing the list
 *   $finder     — everything partials/item-finder needs (see that file)
 *
 * TWO VIEWS, ONE RULE: browsing (no filter) keeps the category sections with
 * their headings — nice to scroll. The moment a filter or search is active the
 * headings would only repeat what the filter already said, so the result is a
 * single flat grid plus the "Showing X of Y" line.
 */
$this->layout('layout', ['title' => 'Your things — Felkyo Creatures']);
?>

<?php /* The category drawings, defined once for every card on the page. */ ?>
<?= $this->insert('partials/item-icons') ?>

<h2 class="panel-label">Your things</h2>

<?php if (empty($groups) && !$isFiltered): ?>
    <p class="empty-state">
        You don&rsquo;t own anything yet — but the village store is warm and Ruinily is in.
    </p>
    <div class="button-row"><a class="btn btn--secondary" href="/shop">Visit the shop</a></div>
<?php else: ?>

    <?= $this->insert('partials/item-finder', ['finder' => $finder]) ?>

    <?php if ($isFiltered): ?>
        <?php if (empty($stacks)): ?>
            <?php /* Never a dead end (golden rule 3): the finder's count line above
                     already offers "Show everything"; this line keeps the empty
                     space itself warm instead of silently blank. */ ?>
            <p class="empty-state">Nothing of yours matches that.</p>
        <?php else: ?>
            <div class="item-grid">
                <?php foreach ($stacks as $stack): ?>
                    <?= $this->insert('partials/item-card', ['stack' => $stack]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <?php $category = $group['category']; ?>

            <h3 class="inventory-type">
                <?php /* The heading carries the icon too, so a section is recognisable
                         while scrolling without reading every word. */ ?>
                <svg class="category-icon" aria-hidden="true" focusable="false">
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
<?php endif; ?>
