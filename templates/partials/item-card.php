<?php
/**
 * One owned item, as a card you can tap.
 *
 * @package Felkyo\Templates
 *
 * Insert it with:
 *   $this->insert('partials/item-card', ['stack' => $stack]);
 * where $stack is an OwnedItemStack. The page must also insert
 * 'partials/item-icons' once, or the little category drawings will be missing.
 *
 * THE THREE SIGNALS. A player should know what a thing is without reading a
 * manual, so every card says its category three times over: as a tint, as an
 * icon, and as a word. That is not belt-and-braces fussiness — it is the only
 * arrangement that works for everybody. The tint is the fastest to read and the
 * one that fails colour-blind players; the icon is recognisable at a glance and
 * fails anyone who has not learnt it yet; the word never fails anyone. Together
 * they cost one line of markup.
 *
 * THE TINT COMES IN AS A VARIABLE, not as a colour. The card sets --card-tint to
 * whatever theme token its category names, and the stylesheet uses that. No
 * colour value is ever written here (CLAUDE.md section 8), so the themes added in
 * M4 restyle every card at once.
 */
$item = $stack->item;
$category = $item->category;

// Show the item's real artwork if it exists, and the category icon if it does
// not. Art arrives file by file as the artist draws it, so this quietly upgrades
// itself: drop mossling-treat.png into public/assets/items/ and the card starts
// showing it, with nothing to edit and nothing to remember.
$artworkFile = dirname(__DIR__, 2) . '/public' . $item->imagePath();
$hasArtwork = is_file($artworkFile);
?>
<?php /* data-category and data-name are the contract with item-finder.js: the
         in-place filtering reads them to decide which cards to hide. They are
         plain data, safe to render everywhere the card appears. */ ?>
<a class="item-card" href="/inventory/<?= $this->e((string) $item->id) ?>"
   data-category="<?= $this->e($category->slug) ?>"
   data-name="<?= $this->e($item->name) ?>"
   style="--card-tint: <?= $this->e($category->colourVariable()) ?>">

    <span class="item-card__art">
        <?php if ($hasArtwork): ?>
            <img class="item-card__img pixelated"
                 src="<?= $this->e($item->imagePath()) ?>"
                 alt="" width="64" height="64">
        <?php else: ?>
            <?php /* No drawing yet, so the category's own icon stands in. It is
                     decorative here — the item's name is right beside it. */ ?>
            <svg class="item-card__placeholder" aria-hidden="true" focusable="false">
                <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
            </svg>
        <?php endif; ?>
    </span>

    <span class="item-card__body">
        <span class="item-card__name"><?= $this->e($item->name) ?></span>

        <span class="item-card__category">
            <svg class="category-icon" aria-hidden="true" focusable="false">
                <use href="#icon-<?= $this->e($category->iconKey) ?>"></use>
            </svg>
            <?= $this->e($category->name) ?>
        </span>
    </span>

    <?php /* "×3" is quick to scan but says nothing on its own to a screen reader,
             so the readable version is carried alongside it and the symbol is
             hidden. Both come from the same number, so they cannot disagree. */ ?>
    <span class="item-card__qty">
        <span aria-hidden="true">&times;<?= $this->e((string) $stack->quantity) ?></span>
        <span class="visually-hidden"><?= $this->e((string) $stack->quantity) ?> owned</span>
    </span>
</a>
