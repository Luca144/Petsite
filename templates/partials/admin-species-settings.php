<?php
/**
 * The species' numbers and flags: price, availability, tastes.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/admin-creature-edit.php. The tastes are radio TILES
 * (never a dropdown): every feedable item plus "no favourite" — short
 * enough to scan while the treat list is small; when it outgrows a screen,
 * the picker gets a search like the shop's finder.
 *
 * Variables: $species (Species), $treats (Item[]).
 */
?>
<div class="card">
    <h3>Numbers &amp; flags</h3>
    <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/settings">
        <?= $this->csrf_field() ?>

        <div class="field">
            <label class="field__label" for="gem_price">Price in the shop (gems)</label>
            <input class="field__input" type="number" id="gem_price" name="gem_price" min="0" step="1"
                   value="<?= $this->e((string) $species->gemPrice) ?>">
        </div>

        <?php /* Checkboxes with their meaning spelled out beside them. */ ?>
        <div class="field admin-species__flags">
            <label class="admin-species__flag">
                <input type="checkbox" name="is_adoptable" value="1" <?= $species->isAdoptable ? 'checked' : '' ?>>
                <span><strong>Players can get one</strong> — it appears in the shop and can be found exploring (once published).</span>
            </label>
            <label class="admin-species__flag">
                <input type="checkbox" name="is_starter" value="1" <?= $species->isStarter ? 'checked' : '' ?>>
                <span><strong>Can be a starter</strong> — new players may begin with one (once published).</span>
            </label>
        </div>

        <fieldset class="admin-roles__choices">
            <legend class="field__label">Favourite treat (eating it delights them)</legend>
            <label class="admin-roles__choice">
                <input type="radio" name="favourite_item_id" value="" <?= $species->favouriteItemId === null ? 'checked' : '' ?>>
                <span class="admin-roles__choice-name">No favourite</span>
            </label>
            <?php foreach ($treats as $treat): ?>
                <label class="admin-roles__choice">
                    <input type="radio" name="favourite_item_id" value="<?= $this->e((string) $treat->id) ?>"
                        <?= $species->favouriteItemId === $treat->id ? 'checked' : '' ?>>
                    <span class="admin-roles__choice-name"><?= $this->e($treat->name) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="admin-roles__choices">
            <legend class="field__label">Least favourite (they eat it, pull a face, and lose nothing)</legend>
            <label class="admin-roles__choice">
                <input type="radio" name="disliked_item_id" value="" <?= $species->dislikedItemId === null ? 'checked' : '' ?>>
                <span class="admin-roles__choice-name">No least favourite</span>
            </label>
            <?php foreach ($treats as $treat): ?>
                <label class="admin-roles__choice">
                    <input type="radio" name="disliked_item_id" value="<?= $this->e((string) $treat->id) ?>"
                        <?= $species->dislikedItemId === $treat->id ? 'checked' : '' ?>>
                    <span class="admin-roles__choice-name"><?= $this->e($treat->name) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <button class="btn btn--primary" type="submit">Save the settings</button>
    </form>
</div>
