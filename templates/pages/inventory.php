<?php
/**
 * The player's inventory, grouped by item type.
 *
 * @package Felkyo\Templates
 *
 * Rendered by InventoryController. $groups is a map of type => list of
 * ['item' => Item, 'quantity' => int], so a new item type simply appears as a new
 * group with no template change.
 */
$this->layout('layout', ['title' => 'Your things — Felkyo Creatures']);
?>

<h2 class="panel-label">Your things</h2>

<?php if (empty($groups)): ?>
    <p class="empty-state">You don&rsquo;t own anything yet. Pop into the shop!</p>
    <div class="button-row"><a class="btn btn--secondary" href="/shop">Visit the shop</a></div>
<?php else: ?>
    <?php foreach ($groups as $type => $entries): ?>
        <h3 class="inventory-type"><?= $this->e(ucfirst($type)) ?>s</h3>
        <div class="inventory-group">
            <?php foreach ($entries as $entry): ?>
                <div class="inventory-item">
                    <span class="inventory-item__name"><?= $this->e($entry['item']->name) ?></span>
                    <span class="inventory-item__qty">&times;<?= $this->e((string) $entry['quantity']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
