<?php
/**
 * Deleting a species or an item for good: what it will touch, then the form.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminRemovalController. Says plainly what happens to players'
 * creatures or items BEFORE anything can be pressed (golden rule 9). The
 * replacement species is chosen from tappable radio tiles, never a dropdown.
 *
 * Variables: $kind ('species'|'item'), $name, $isDraft, $backTo, $action,
 * $affected (int: creatures of the species / items owned), $refundEach
 * (items only), $replacements (Species[], species only).
 */
$this->layout('layout', ['title' => 'Delete ' . $name . ' — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Delete &ldquo;<?= $this->e($name) ?>&rdquo; for good?</h2>
        <p><a href="<?= $this->e($backTo) ?>">&larr; no, take me back</a></p>
    </header>

    <?php if (!$isDraft): ?>
        <div class="card">
            <p><strong>It's still published.</strong> Take it back to draft on <a href="<?= $this->e($backTo) ?>">its page</a> first &mdash;
                that stops anyone new getting one, and gives you a moment to be sure.</p>
        </div>
    <?php else: ?>
        <div class="card">
            <h3>What happens</h3>
            <?php if ($kind === 'species'): ?>
                <?php if ($affected === 0): ?>
                    <p>Nobody has one, so nothing else changes. Its pictures stay in the art store.</p>
                <?php else: ?>
                    <p><strong><?= $this->e((string) $affected) ?> creatures</strong> are <?= $this->e($name) ?>s.
                        They'll become the species you choose below &mdash; same names, levels and history, just a new look.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>It comes off every shop shelf and loot table.
                    <?php if ($affected > 0): ?>
                        Players own <strong><?= $this->e((string) $affected) ?></strong>; each is paid back
                        <strong><?= $this->e((string) $refundEach) ?> gems</strong> before it disappears.
                    <?php else: ?>
                        Nobody owns one.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <p>This can't be undone.</p>
        </div>

        <form class="card" method="post" action="<?= $this->e($action) ?>">
            <?= $this->csrf_field() ?>
            <?php if ($kind === 'species' && $affected > 0): ?>
                <fieldset class="admin-trait__choice">
                    <legend class="field__label">Its creatures become&hellip;</legend>
                    <?php foreach ($replacements as $other): ?>
                        <label class="admin-trait__pill">
                            <input type="radio" name="replacement_id" value="<?= $this->e((string) $other->id) ?>" required>
                            <?= $this->e($other->name) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>
            <div class="field">
                <label class="field__label" for="confirm_name">To confirm, type its name: <?= $this->e($name) ?></label>
                <input class="field__input" type="text" id="confirm_name" name="confirm_name" autocomplete="off" required>
            </div>
            <button class="btn btn--secondary" type="submit">Delete <?= $this->e($name) ?> for good</button>
        </form>
    <?php endif; ?>
</section>
