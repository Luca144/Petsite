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
 * Variables: $shop (Shop), $items (Item[] — already narrowed by the finder),
 * $isFiltered (bool), $finder (see partials/item-finder), $flash (string|null).
 */
// For the versioned address of the finder's enhancement script below.
use Felkyo\Http\AssetUrl;

$this->layout('layout', ['title' => $shop->name . ' — Felkyo Creatures']);
?>

<?= $this->insert('partials/item-icons') ?>

<?php /* The shopfront — background, keeper, greeting, all panel-set data.
         Its own partial (M2.4), shared-ready for the day more shops get
         doors. */ ?>
<?= $this->insert('partials/shopfront', ['shop' => $shop]) ?>

<?php if (!empty($currentUser)): ?>
    <?php /* The purse, right where the deciding happens. It is also in the page
             footer, but "can I afford this?" is asked HERE, and the answer should
             not live a full page-scroll away from the question. */ ?>
    <p class="shop-purse">
        <span aria-hidden="true">&#9670;</span>
        <?= $this->t('shop.you-re-carrying', 'You’re carrying {currency_balance} {coins}.', ['currency_balance' => (string) $currentUser->currencyBalance, 'coins' => $currencyName ?? 'coins']) ?>
    </p>
<?php endif; ?>

<?php /* CREATURES FIRST, and above the finder on purpose.
         A creature is not a thing on a shelf, and it should not be filtered
         alongside the treats — so this section sits outside the finder entirely,
         which also means item-finder.js can never hide it by accident.
         It is what most people come here for; the shelves can wait a screen. */ ?>
<?php if (!empty($creaturesForSale)): ?>
    <section class="creature-shop" aria-labelledby="creature-shop-heading">
        <h3 class="panel-label" id="creature-shop-heading"><?= $this->t('shop.creatures-looking-for-a-home', 'Creatures looking for a home') ?></h3>
        <p class="creature-shop__intro">
            <?= $this->t('shop.each-one-comes-with-a', 'Each one comes with a name you can change straight away.') ?>
        </p>

        <div class="creature-shop__grid">
            <?php foreach ($creaturesForSale as $species): ?>
                <?php
                // A creature on the shelf is shown as a baby — that is what you
                // would be taking home. The address comes from the species'
                // own resolver (art-store picture or the old convention —
                // Species::spriteUrlFor), with the same whole-multiple sizing
                // (PixelArt): a fractional scale smears pixel art.
                $canAfford = !empty($currentUser) && $currentUser->currencyBalance >= $species->gemPrice;
                $shelfImage = $species->spriteUrlFor('baby');
                [$artWidth, $artHeight] = \Felkyo\Design\PixelArt::displaySize($shelfImage, 104);
                ?>
                <div class="creature-shop__offer">
                    <span class="creature-shop__art">
                        <img class="creature-shop__img pixelated"
                             src="<?= $this->e($shelfImage) ?>"
                             alt="<?= $this->e('A baby ' . $species->name) ?>"
                             width="<?= $this->e((string) $artWidth) ?>" height="<?= $this->e((string) $artHeight) ?>">
                    </span>

                    <span class="creature-shop__name"><?= $this->e($species->name) ?></span>

                    <?php if ($species->flavourText !== null): ?>
                        <span class="creature-shop__flavour"><?= $this->e($species->flavourText) ?></span>
                    <?php endif; ?>

                    <?php /* The price is written out with its unit ("50 gems", not
                             just "50"), because a bare number beside a picture is
                             a guess. */ ?>
                    <span class="creature-shop__price">
                        <span aria-hidden="true">&#9670;</span>
                        <?= $this->e((string) $species->gemPrice) ?>
                        <?= $this->e($currencyName ?? 'gems') ?>
                    </span>

                    <form method="post" action="/shop/creature">
                        <?= $this->csrf_field() ?>
                        <input type="hidden" name="species_id" value="<?= $this->e((string) $species->id) ?>">
                        <?php /* The button is NEVER disabled when somebody cannot
                                 afford it. A disabled button says "no" and nothing
                                 else; pressing this one says exactly how many gems
                                 short you are and where they come from, which is
                                 golden rule 3. The server refuses either way. */ ?>
                        <button class="btn <?= $canAfford ? 'btn--primary' : 'btn--secondary' ?>" type="submit">
                            <?= $this->t('shop.take-home', 'Take home') ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <hr class="rule">
<?php endif; ?>

<h3 class="panel-label"><?= $this->t('shop.on-the-shelves', 'On the shelves') ?></h3>

<?= $this->insert('partials/item-finder', ['finder' => $finder]) ?>

<?php /* Never a dead end: the finder's count line above carries the "show
         everything" way back; this keeps the empty shelf itself friendly. It is
         always in the page (hidden) so the no-reload filtering in
         item-finder.js can reveal it without a round trip. */ ?>
<p class="empty-state finder-empty" <?= $isFiltered && empty($items) ? '' : 'hidden' ?>>
    <?= $this->e($finder['emptyLine']) ?>
</p>

<div class="shop-items">
    <?php foreach ($items as $item): ?>
        <?php
        $category = $item->category;
        // Same three-source cascade as the inventory card (M2.4): the linked
        // art-store icon, else the old convention file, else the category icon.
        $conventionFile = dirname(__DIR__, 2) . '/public' . $item->conventionPath();
        $iconSrc = $item->hasStoredIcon()
            ? $item->iconUrl()
            : (is_file($conventionFile) ? $item->conventionPath() : null);
        $hasArtwork = $iconSrc !== null;
        ?>
        <?php /* data-category / data-name: the contract with item-finder.js,
                 which filters these cards in place without a reload. */ ?>
        <div class="shop-item"
             data-category="<?= $this->e($category->slug) ?>"
             data-name="<?= $this->e($item->name) ?>"
             style="--card-tint: <?= $this->e($category->colourVariable()) ?>">
            <span class="shop-item__art">
                <?php if ($hasArtwork): ?>
                    <img class="pixelated" src="<?= $this->e($iconSrc) ?>"
                         alt="" width="52" height="52">
                <?php else: ?>
                    <svg class="shop-item__placeholder" aria-hidden="true" focusable="false">
                        <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
                    </svg>
                <?php endif; ?>
            </span>

            <a class="shop-item__name" href="/library/item/<?= $this->e((string) $item->id) ?>"><?= $this->e($item->name) ?></a>

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
                    <?= $this->t('shop.buy', 'Buy') ?>
                    <span class="visually-hidden"><?= $this->e($item->name) ?></span>
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<?php /* Filters the shelves in place, with no reload — and without it, every
         pill and the search form work as plain links (see the file's header). */ ?>
<script src="<?= AssetUrl::versioned('/js/item-finder.js') ?>" defer></script>
