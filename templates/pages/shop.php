<?php
/**
 * The shop — its stock, with a "Buy" button for each item.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ShopController. Each item shows its price and a small form that
 * posts the purchase. The player's balance is shown in the site header.
 *
 * Variables: $shop (Shop), $items (Item[]), $flash (string|null).
 */
$this->layout('layout', ['title' => $shop->name . ' — Felkyo Creatures']);
?>

<?php if (!empty($flash)): ?>
    <p class="flash" role="status"><?= $this->e($flash) ?></p>
<?php endif; ?>

<h2 class="panel-label"><?= $this->e($shop->name) ?></h2>
<?php if ($shop->description !== null): ?>
    <p><?= $this->e($shop->description) ?></p>
<?php endif; ?>

<div class="shop-items">
    <?php foreach ($items as $item): ?>
        <div class="shop-item">
            <span class="shop-item__name"><?= $this->e($item->name) ?></span>
            <?php if ($item->description !== null): ?>
                <span class="shop-item__desc"><?= $this->e($item->description) ?></span>
            <?php endif; ?>
            <span class="shop-item__price">
                <span aria-hidden="true">&#9670;</span> <?= $this->e((string) $item->price) ?>
            </span>
            <form method="post" action="/shop/buy">
                <?= $this->csrf_field() ?>
                <input type="hidden" name="item_id" value="<?= $this->e((string) $item->id) ?>">
                <button class="btn btn--secondary" type="submit">Buy</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
