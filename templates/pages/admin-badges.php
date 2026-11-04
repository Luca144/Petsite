<?php
/**
 * The panel's badge editor: every keepsake badge as a small form, plus one
 * for a new badge.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminBadgesController::index. Who a badge is for is chosen
 * with radio pills (never a dropdown).
 *
 * Variable: $badges (list of badge arrays).
 */
$this->layout('layout', ['title' => 'Badges — Felkyo Creatures']);
$forms = array_merge([['id' => 0, 'name' => '', 'description' => '', 'awarded_to' => 'creature', 'kept_days' => 30, 'art_name' => null]], $badges);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Badges</h2>
        <p>
            <strong>Creature badges</strong> arrive by themselves once a creature has been in Felkyo for the
            number of days you set &mdash; earned by time, never lost. <strong>Player badges</strong> are special:
            only the owner gives them, from the <a href="/admin/gifts">gift desk</a>.
            Pictures are uploaded as &ldquo;badge&rdquo; in <a href="/admin/pictures">Pictures</a>.
        </p>
        <p><a href="/admin">&larr; back to the panel</a></p>
    </header>

    <?php foreach ($forms as $badge): ?>
        <?php $id = (string) $badge['id']; ?>
        <form class="card admin-trait" method="post" action="/admin/badges/<?= $this->e($id) ?>">
            <?= $this->csrf_field() ?>
            <h3>
                <?php if ($badge['art_name'] !== null): ?>
                    <img class="pixelated" src="/art/<?= $this->e($badge['art_name']) ?>" alt="" width="32" height="32">
                <?php endif; ?>
                <?= $badge['id'] === 0 ? 'A new badge' : $this->e($badge['name']) ?>
            </h3>
            <div class="field">
                <label class="field__label" for="badge-name-<?= $this->e($id) ?>">Name</label>
                <input class="field__input" type="text" id="badge-name-<?= $this->e($id) ?>" name="name" maxlength="40" value="<?= $this->e($badge['name']) ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="badge-words-<?= $this->e($id) ?>">A few words (shown beside it)</label>
                <input class="field__input" type="text" id="badge-words-<?= $this->e($id) ?>" name="description" maxlength="200" value="<?= $this->e($badge['description']) ?>" required>
            </div>
            <fieldset class="admin-trait__choice">
                <legend class="field__label">Who it's for</legend>
                <label class="admin-trait__pill">
                    <input type="radio" name="awarded_to" value="creature" <?= $badge['awarded_to'] === 'creature' ? 'checked' : '' ?>> a creature, after a while
                </label>
                <label class="admin-trait__pill">
                    <input type="radio" name="awarded_to" value="player" <?= $badge['awarded_to'] === 'player' ? 'checked' : '' ?>> a player, as a special gift
                </label>
            </fieldset>
            <div class="field">
                <label class="field__label" for="badge-days-<?= $this->e($id) ?>">Creature badges: after how many days</label>
                <input class="field__input" type="number" id="badge-days-<?= $this->e($id) ?>" name="kept_days" min="1" max="3650" value="<?= $this->e((string) ($badge['kept_days'] ?? 30)) ?>">
            </div>
            <div class="admin-species__actions">
                <button class="btn btn--primary" type="submit"><?= $badge['id'] === 0 ? 'Add this badge' : 'Save' ?></button>
                <?php if ($badge['id'] !== 0): ?>
                    <a class="btn btn--secondary" href="/admin/badges/<?= $this->e($id) ?>/picture">Choose a picture</a>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($badge['id'] !== 0): ?>
            <form class="admin-trait__delete" method="post" action="/admin/badges/<?= $this->e($id) ?>/delete"
                  onsubmit="return confirm('Delete this badge? Everyone who has it loses it.');">
                <?= $this->csrf_field() ?>
                <button class="btn--quiet" type="submit">delete &ldquo;<?= $this->e($badge['name']) ?>&rdquo;</button>
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
