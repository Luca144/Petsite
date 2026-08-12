<?php
/**
 * The player's inventory, grouped by item type.
 *
 * @package Felkyo\Templates
 *
 * Rendered by InventoryController. $groups is a map of type => list of
 * OwnedItemStack (one pile of identical items each), so a new item type simply
 * appears as a new group with no template change.
 *
 * The coloured item card with its category, icon and actions arrives in M1.2 —
 * this is still the plain list, reading from the new shape.
 */
$this->layout('layout', ['title' => 'Your things — Felkyo Creatures']);
?>

<h2 class="panel-label">Your things</h2>

<?php if (empty($groups)): ?>
    <p class="empty-state">You don&rsquo;t own anything yet. Pop into the shop!</p>
    <div class="button-row"><a class="btn btn--secondary" href="/shop">Visit the shop</a></div>
<?php else: ?>
    <?php foreach ($groups as $type => $stacks): ?>
        <h3 class="inventory-type"><?= $this->e(ucfirst($type)) ?>s</h3>
        <div class="inventory-group">
            <?php foreach ($stacks as $stack): ?>
                <div class="inventory-item">
                    <span class="inventory-item__name"><?= $this->e($stack->item->name) ?></span>
                    <span class="inventory-item__qty">&times;<?= $this->e((string) $stack->quantity) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
