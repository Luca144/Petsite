<?php
/**
 * One item, everything about it, edited in place.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminItemEditController (Artist role). Sections with their
 * own save buttons, like every editor; the numbers section lives in a
 * partial to keep this file inside its limit. The "where is this used"
 * list is the answer to "why is this slug spoken for" (slugs are minted
 * once and never editable — decisions.md 2026-08-31).
 *
 * Variables: $item (Item), $categories (ItemCategory[]),
 * $missing (string[]), $references (string[]), $versions (arrays).
 */
$this->layout('layout', ['title' => $this->e($item->name) . ' — Items — Felkyo Creatures']);

// The same three-source icon cascade the player pages use.
$conventionFile = dirname(__DIR__, 2) . '/public' . $item->conventionPath();
$iconSrc = $item->hasStoredIcon()
    ? $item->iconUrl()
    : (is_file($conventionFile) ? $item->conventionPath() : null);
?>

<?= $this->insert('partials/item-icons') ?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($item->name) ?></h2>
        <p class="admin-species__statusline">
            <span class="admin-species__status admin-species__status--<?= $item->isPublished() ? 'published' : 'draft' ?>">
                <?= $item->isPublished() ? 'published' : 'draft' ?>
            </span>
            <?php if (!$item->isPublished() && $missing !== []): ?>
                <span class="admin-species__missing">still needs: <?= $this->e(implode(', ', $missing)) ?></span>
            <?php endif; ?>
        </p>
        <?php if ($references !== []): ?>
            <p class="field__hint">Woven into the game: <?= $this->e(implode('; ', $references)) ?>.</p>
        <?php endif; ?>

        <div class="admin-species__actions">
            <?php if ($item->isPublished()): ?>
                <form method="post" action="/admin/items/<?= $this->e((string) $item->id) ?>/unpublish">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--secondary" type="submit">Back to draft</button>
                </form>
                <p class="field__hint">Back to draft: off every shelf; what players already own keeps working.</p>
            <?php else: ?>
                <form method="post" action="/admin/items/<?= $this->e((string) $item->id) ?>/publish">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Publish</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <div class="card admin-stage">
        <h3>Its picture</h3>
        <div class="admin-stage__row">
            <span class="admin-stage__art">
                <?php if ($iconSrc !== null): ?>
                    <img class="pixelated" src="<?= $this->e($iconSrc) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <svg class="item-card__placeholder" aria-hidden="true" focusable="false">
                        <use href="#icon-<?= $this->e($item->category->iconKey) ?>"></use>
                    </svg>
                <?php endif; ?>
            </span>
            <div class="admin-stage__side">
                <a class="btn btn--secondary" href="/admin/items/<?= $this->e((string) $item->id) ?>/icon">
                    Choose an icon
                </a>
                <?php if (!$item->hasStoredIcon()): ?>
                    <p class="field__hint">No icon chosen — the category&rsquo;s drawing stands in, as it always has.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Names &amp; words</h3>
        <form method="post" action="/admin/items/<?= $this->e((string) $item->id) ?>/words">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Name</label>
                <input class="field__input" type="text" id="name" name="name"
                       value="<?= $this->e($item->name) ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="description">Description (shown on its card and in the shop)</label>
                <textarea class="field__input" id="description" name="description" rows="2"><?= $this->e((string) $item->description) ?></textarea>
            </div>
            <button class="btn btn--primary" type="submit">Save the words</button>
        </form>
    </div>

    <?= $this->insert('partials/admin-item-numbers', ['item' => $item, 'categories' => $categories]) ?>

    <div class="card">
        <h3>Versions</h3>
        <p class="field__hint">Every save is kept (newest 20). Restoring never touches whether the item is published.</p>
        <?php if (empty($versions)): ?>
            <p>No history yet — it starts with the first save.</p>
        <?php else: ?>
            <ul class="admin-species__versions">
                <?php foreach ($versions as $version): ?>
                    <li class="admin-species__version">
                        <span class="admin-species__version-when"><?= $this->e($version['created_at']) ?></span>
                        <span>by <?= $this->e($version['saved_by'] ?? 'the site') ?></span>
                        <span>as &ldquo;<?= $this->e((string) ($version['snapshot']['name'] ?? '?')) ?>&rdquo;</span>
                        <form method="post" action="/admin/items/<?= $this->e((string) $item->id) ?>/revert">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="version_id" value="<?= $this->e((string) $version['id']) ?>">
                            <button type="submit" class="btn--quiet">restore this</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
