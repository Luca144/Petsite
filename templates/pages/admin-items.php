<?php
/**
 * The item list — everything the economy holds, and the door to a new item.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminItemsController (Artist role). One card per item, its
 * status as a word, drafts saying what they still need. Clicking a card
 * opens its editor.
 *
 * Variables: $cards (arrays of item => Item, missing => string[]),
 * $categories (ItemCategory[]).
 */
$this->layout('layout', ['title' => 'Items — Felkyo Creatures']);
?>

<?= $this->insert('partials/item-icons') ?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Items</h2>
        <p>
            Everything that can be owned, eaten and sold. Drafts are on no
            shelf until you publish them.
        </p>
    </header>

    <div class="card admin-species__new">
        <h3>Start a new item</h3>
        <form method="post" action="/admin/items/create">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Its name</label>
                <input class="field__input" type="text" id="name" name="name" required
                       placeholder="e.g. Moss Cake">
            </div>
            <fieldset class="admin-roles__choices">
                <legend class="field__label">What kind of thing is it?</legend>
                <?php foreach ($categories as $category): ?>
                    <label class="admin-roles__choice">
                        <input type="radio" name="category_id" value="<?= $this->e((string) $category->id) ?>" required>
                        <span class="admin-roles__choice-name"><?= $this->e($category->name) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button class="btn btn--primary" type="submit">Start it (as a draft)</button>
        </form>
    </div>

    <?php if (empty($cards)): ?>
        <div class="card"><p>No items yet — the first one starts above.</p></div>
    <?php else: ?>
        <ul class="admin-species__grid">
            <?php foreach ($cards as $card): ?>
                <?php
                $item = $card['item'];
                // The same three-source icon cascade the player pages use.
                $conventionFile = dirname(__DIR__, 2) . '/public' . $item->conventionPath();
                $iconSrc = $item->hasStoredIcon()
                    ? $item->iconUrl()
                    : (is_file($conventionFile) ? $item->conventionPath() : null);
                ?>
                <li>
                    <a class="admin-species__card card" href="/admin/items/<?= $this->e((string) $item->id) ?>">
                        <span class="admin-species__face">
                            <?php if ($iconSrc !== null): ?>
                                <img class="pixelated" src="<?= $this->e($iconSrc) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <svg class="item-card__placeholder" aria-hidden="true" focusable="false">
                                    <use href="#icon-<?= $this->e($item->category->iconKey) ?>"></use>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <span class="admin-species__name">
                            <?= $this->e($item->name) ?>
                            <span class="admin-audit__subject"><?= $this->e($item->category->name) ?> &middot; <?= $this->e((string) $item->price) ?> gems</span>
                        </span>
                        <span class="admin-species__status admin-species__status--<?= $item->isPublished() ? 'published' : 'draft' ?>">
                            <?= $item->isPublished() ? 'published' : 'draft' ?>
                        </span>
                        <?php if ($card['missing'] !== []): ?>
                            <span class="admin-species__missing">
                                still needs: <?= $this->e(implode(', ', $card['missing'])) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
