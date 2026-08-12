<?php
/**
 * The shop — its stock, with a "Buy" button for each item.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ShopController. Each item shows its price and a small form that
 * posts the purchase.
 *
 * The cards speak the same visual language as the inventory's item cards: the
 * category arrives three ways at once — as a tint, as an icon, and as a word —
 * so what you see in the shop window is what you will recognise on your own
 * shelf afterwards. (See partials/item-card.php for why all three signals.)
 *
 * Variables: $shop (Shop), $items (Item[]), $flash (string|null).
 */
$this->layout('layout', ['title' => $shop->name . ' — Felkyo Creatures']);
?>

<?= $this->insert('partials/item-icons') ?>

<?php if (!empty($flash)): ?>
    <p class="flash" role="status"><?= $this->e($flash) ?></p>
<?php endif; ?>

<h2 class="panel-label"><?= $this->e($shop->name) ?></h2>
<?php if ($shop->description !== null): ?>
    <p><?= $this->e($shop->description) ?></p>
<?php endif; ?>

<?php if (!empty($currentUser)): ?>
    <?php /* The purse, right where the deciding happens. It is also in the page
             footer, but "can I afford this?" is asked HERE, and the answer should
             not live a full page-scroll away from the question. */ ?>
    <p class="shop-purse">
        <span aria-hidden="true">&#9670;</span>
        You&rsquo;re carrying <?= $this->e((string) $currentUser->currencyBalance) ?>
        <?= $this->e($currencyName ?? 'coins') ?>.
    </p>
<?php endif; ?>

<div class="shop-items">
    <?php foreach ($items as $item): ?>
        <?php
        $category = $item->category;
        // Same convention as the inventory card: the item's real artwork when the
        // artist has drawn it, the category's icon standing in until then.
        $artworkFile = dirname(__DIR__, 2) . '/public' . $item->imagePath();
        $hasArtwork = is_file($artworkFile);
        ?>
        <div class="shop-item" style="--card-tint: <?= $this->e($category->colourVariable()) ?>">
            <span class="shop-item__art">
                <?php if ($hasArtwork): ?>
                    <img class="pixelated" src="<?= $this->e($item->imagePath()) ?>"
                         alt="" width="52" height="52">
                <?php else: ?>
                    <svg class="shop-item__placeholder" aria-hidden="true" focusable="false">
                        <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
                    </svg>
                <?php endif; ?>
            </span>

            <span class="shop-item__name"><?= $this->e($item->name) ?></span>

            <span class="shop-item__category">
                <svg class="category-icon" aria-hidden="true" focusable="false">
                    <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
                </svg>
                <?= $this->e($category->name) ?>
            </span>

            <?php if ($item->description !== null): ?>
                <span class="shop-item__desc"><?= $this->e($item->description) ?></span>
            <?php endif; ?>

            <?php /* The unit is written out ("8 coins", not just "8") — plain
                     words, and nothing left for a screen reader to guess at. The
                     diamond is decoration beside the words, never instead. */ ?>
            <span class="shop-item__price">
                <span aria-hidden="true">&#9670;</span>
                <?= $this->e((string) $item->price) ?> <?= $this->e($currencyName ?? 'coins') ?>
            </span>

            <form method="post" action="/shop/buy">
                <?= $this->csrf_field() ?>
                <input type="hidden" name="item_id" value="<?= $this->e((string) $item->id) ?>">
                <button class="btn btn--secondary" type="submit">
                    Buy
                    <span class="visually-hidden"><?= $this->e($item->name) ?></span>
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>
