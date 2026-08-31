<?php
/**
 * One area, everything about it, edited in place.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminAreaEditController (Artist role). The scene-and-spots
 * section lives in its own partial (it carries the click-to-place layer);
 * the loot table is the plain-line field the M2.5 plan promised, with the
 * current table shown readably beside it.
 *
 * Variables: $area (Area), $lootRows (arrays from lootFor), $lootText
 * (string), $lootWasRefused (bool), $missing (string[]), $versions (arrays).
 */
use Felkyo\Http\AssetUrl;

$this->layout('layout', ['title' => $this->e($area->name) . ' — Areas — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($area->name) ?></h2>
        <p class="admin-species__statusline">
            <span class="admin-species__status admin-species__status--<?= $area->isPublished() ? 'published' : 'draft' ?>">
                <?= $area->isPublished() ? 'published' : 'draft' ?>
            </span>
            <?php if (!$area->isPublished() && $missing !== []): ?>
                <span class="admin-species__missing">still needs: <?= $this->e(implode(', ', $missing)) ?></span>
            <?php endif; ?>
        </p>

        <div class="admin-species__actions">
            <?php if ($area->isPublished()): ?>
                <a class="btn btn--secondary" href="/explore/<?= $this->e($area->slug) ?>">See the real page</a>
                <form method="post" action="/admin/areas/<?= $this->e((string) $area->id) ?>/unpublish">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--secondary" type="submit">Back to draft</button>
                </form>
            <?php else: ?>
                <form method="post" action="/admin/areas/<?= $this->e((string) $area->id) ?>/publish">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Publish</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <div class="card">
        <h3>Names &amp; words</h3>
        <form method="post" action="/admin/areas/<?= $this->e((string) $area->id) ?>/words">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Name</label>
                <input class="field__input" type="text" id="name" name="name"
                       value="<?= $this->e($area->name) ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="description">Description (under the name on the area page)</label>
                <?php /* rows=3, not 2: at 360px the seeded description runs to three
         lines, and two rows clipped the third mid-letter (seen in the
         gui-shots — golden rule 12). */ ?>
                <textarea class="field__input" id="description" name="description" rows="3"><?= $this->e((string) $area->description) ?></textarea>
            </div>
            <button class="btn btn--primary" type="submit">Save the words</button>
        </form>
    </div>

    <?= $this->insert('partials/admin-area-scene', ['area' => $area]) ?>

    <div class="card">
        <h3>The loot table</h3>
        <p class="field__hint">
            One reward per line: <code>weight; what; message</code> — where
            <code>what</code> is <code>nothing</code>, <code>creature</code>,
            or <code>item: the-items-address</code>. Weights are relative
            whole numbers (at least 1); paste from anywhere. Saving takes all
            lines or none — a typo never costs you the rest.
        </p>

        <?php if (!empty($lootRows)): ?>
            <ul class="admin-loot__current">
                <?php foreach ($lootRows as $row): ?>
                    <li>
                        <span class="admin-loot__weight"><?= $this->e((string) $row['weight']) ?></span>
                        <span class="admin-loot__what"><?= $this->e($row['type'] === 'item' ? ($row['item_name'] ?? $row['item'] ?? '?') : $row['type']) ?></span>
                        <span class="admin-loot__message">&ldquo;<?= $this->e($row['message']) ?>&rdquo;</span>
                        <?php if (($row['item_status'] ?? '') === 'draft'): ?>
                            <span class="admin-species__missing">draft item</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/admin/areas/<?= $this->e((string) $area->id) ?>/loot">
            <?= $this->csrf_field() ?>
            <?php if (!empty($lootWasRefused)): ?>
                <p class="admin-pictures__warning" role="alert">
                    This is your last try, kept exactly as you typed it — the
                    reasons it was refused are in the message above.
                </p>
            <?php endif; ?>
            <div class="field">
                <label class="field__label" for="loot">The table</label>
                <textarea class="field__input admin-loot__field" id="loot" name="loot"
                          rows="<?= max(6, count($lootRows) + 3) ?>" spellcheck="false"><?= $this->e($lootText) ?></textarea>
            </div>
            <button class="btn btn--primary" type="submit">Save the table</button>
        </form>
    </div>

    <div class="card">
        <h3>Versions</h3>
        <p class="field__hint">Every save is kept (newest 20). Restoring brings the spots and the table back too — and never touches whether the area is published.</p>
        <?php if (empty($versions)): ?>
            <p>No history yet — it starts with the first save.</p>
        <?php else: ?>
            <ul class="admin-species__versions">
                <?php foreach ($versions as $version): ?>
                    <li class="admin-species__version">
                        <span class="admin-species__version-when"><?= $this->e($version['created_at']) ?></span>
                        <span>by <?= $this->e($version['saved_by'] ?? 'the site') ?></span>
                        <span>as &ldquo;<?= $this->e((string) ($version['snapshot']['name'] ?? '?')) ?>&rdquo;</span>
                        <form method="post" action="/admin/areas/<?= $this->e((string) $area->id) ?>/revert">
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

<?php /* The click-to-place layer for the scene — pure enhancement; the
         number fields in the partial are the real form and work without it. */ ?>
<script src="<?= AssetUrl::versioned('/js/area-spots.js') ?>" defer></script>
