<?php
/**
 * The item's numbers: prices, treat effects, category.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/admin-item-edit.php. The margin rule is stated BESIDE
 * the fields it governs, and enforced at save with the same words — nobody
 * should meet a rule for the first time in a refusal.
 *
 * Variables: $item (Item), $categories (ItemCategory[]).
 */
?>
<div class="card">
    <h3>Numbers</h3>
    <form method="post" action="/admin/items/<?= $this->e((string) $item->id) ?>/numbers">
        <?= $this->csrf_field() ?>

        <div class="field">
            <label class="field__label" for="price">Price in the shop (gems)</label>
            <input class="field__input" type="number" id="price" name="price" min="0" step="1"
                   value="<?= $this->e((string) $item->price) ?>">
        </div>
        <div class="field">
            <label class="field__label" for="sell_value">What a player gets for selling one back (gems)</label>
            <input class="field__input" type="number" id="sell_value" name="sell_value" min="0" step="1"
                   value="<?= $this->e((string) $item->sellValue) ?>">
            <p class="field__hint">
                At most 80% of the price — selling back must pay less than
                buying costs, or two clicks become a money machine. 0 means
                &ldquo;no shop would buy this&rdquo;.
            </p>
        </div>

        <div class="field">
            <label class="field__label" for="happiness_bonus">Happiness from eating one (0 = not food)</label>
            <input class="field__input" type="number" id="happiness_bonus" name="happiness_bonus" min="0" max="100" step="1"
                   value="<?= $this->e((string) $item->happinessBonus) ?>">
        </div>
        <div class="field">
            <label class="field__label" for="energy_bonus">Energy from eating one</label>
            <input class="field__input" type="number" id="energy_bonus" name="energy_bonus" min="0" max="100" step="1"
                   value="<?= $this->e((string) $item->energyBonus) ?>">
            <p class="field__hint">Anything with a bonus counts as a treat and appears in feeding — that IS the rule, there is no separate switch.</p>
        </div>

        <fieldset class="admin-roles__choices">
            <legend class="field__label">Category</legend>
            <?php foreach ($categories as $category): ?>
                <label class="admin-roles__choice">
                    <input type="radio" name="category_id" value="<?= $this->e((string) $category->id) ?>"
                        <?= $item->category->id === $category->id ? 'checked' : '' ?>>
                    <span class="admin-roles__choice-name"><?= $this->e($category->name) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <button class="btn btn--primary" type="submit">Save the numbers</button>
    </form>
</div>
