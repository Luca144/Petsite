<?php
/**
 * The owner's gift desk: give a player gems, items or a creature.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminGiftsController::show. One form, three kinds of gift,
 * all chosen with radio tiles (never dropdowns). Fields for the kinds you
 * didn't pick are simply ignored — nothing depends on scripting.
 *
 * Variables: $species (Species[]), $items (Item[]), $caps (array), $to (string).
 */
$this->layout('layout', ['title' => 'Gifts — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Gifts</h2>
        <p>
            Give a player gems, items or a creature &mdash; to make something right, for an event prize,
            or just because. Every gift is written to the audit log with its reason. You can't give to yourself.
        </p>
        <p><a href="/admin">&larr; back to the panel</a></p>
    </header>

    <form class="card" method="post" action="/admin/gifts">
        <?= $this->csrf_field() ?>
        <div class="field">
            <label class="field__label" for="username">Who it's for (their name)</label>
            <input class="field__input" type="text" id="username" name="username" maxlength="40" required value="<?= $this->e($to) ?>">
        </div>

        <fieldset class="admin-trait__choice">
            <legend class="field__label">1. What to give</legend>
            <label class="admin-trait__pill"><input type="radio" name="kind" value="gems" checked> gems</label>
            <label class="admin-trait__pill"><input type="radio" name="kind" value="item"> items</label>
            <label class="admin-trait__pill"><input type="radio" name="kind" value="creature"> a creature</label>
        </fieldset>

        <div class="field">
            <label class="field__label" for="amount">How many (gems: up to <?= $this->e((string) $caps['max_gems']) ?>; items: up to <?= $this->e((string) $caps['max_items']) ?>)</label>
            <input class="field__input" type="number" id="amount" name="amount" min="1" value="1">
        </div>

        <fieldset class="admin-trait__choice">
            <legend class="field__label">For items: which one</legend>
            <?php foreach ($items as $item): ?>
                <label class="admin-trait__pill">
                    <input type="radio" name="item_id" value="<?= $this->e((string) $item->id) ?>"> <?= $this->e($item->name) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="admin-trait__choice">
            <legend class="field__label">For a creature: which species</legend>
            <?php foreach ($species as $one): ?>
                <label class="admin-trait__pill">
                    <input type="radio" name="species_id" value="<?= $this->e((string) $one->id) ?>"> <?= $this->e($one->name) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="field">
            <label class="field__label" for="creature_name">For a creature: its name (optional)</label>
            <input class="field__input" type="text" id="creature_name" name="creature_name" maxlength="30">
        </div>

        <div class="field">
            <label class="field__label" for="reason">2. Why (for the audit log)</label>
            <input class="field__input" type="text" id="reason" name="reason" maxlength="200" required>
        </div>

        <button class="btn btn--primary" type="submit">Give it</button>
    </form>
</section>
