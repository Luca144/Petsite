<?php
/**
 * One trait's edit form on the panel's character page — or, with
 * $trait = null, the form for a brand-new one.
 *
 * @package Felkyo\Templates
 *
 * No dropdowns (CLAUDE.md §9): the tier and "earned by" are radio buttons
 * laid out as tappable pills. Fields that only matter for one tier say so in
 * their label rather than hiding — nothing depends on scripting.
 *
 * Variable: $trait (?CreatureTrait).
 */
use Felkyo\Character\CreatureTrait;

$id = $trait === null ? '0' : (string) $trait->id;
$prefix = 'trait-' . $id . '-';
$tierWords = [
    CreatureTrait::TIER_EARNED => 'earned by living',
    CreatureTrait::TIER_SHELTER => 'given by the pound',
    CreatureTrait::TIER_GIFT => 'a lucky gift on adoption',
];
?>
<form class="card admin-trait" method="post" action="/admin/character/traits/<?= $this->e($id) ?>">
    <?= $this->csrf_field() ?>
    <h3><?= $trait === null ? 'A new trait' : $this->e($trait->name) ?></h3>

    <div class="field">
        <label class="field__label" for="<?= $this->e($prefix) ?>name">Name</label>
        <input class="field__input" type="text" id="<?= $this->e($prefix) ?>name" name="name" maxlength="40"
               value="<?= $this->e($trait?->name ?? '') ?>" required>
    </div>
    <div class="field">
        <label class="field__label" for="<?= $this->e($prefix) ?>description">What it means (shown on the creature's page)</label>
        <input class="field__input" type="text" id="<?= $this->e($prefix) ?>description" name="description" maxlength="200"
               value="<?= $this->e($trait?->description ?? '') ?>" required>
    </div>

    <fieldset class="admin-trait__choice">
        <legend class="field__label">What kind of trait</legend>
        <?php foreach ($tierWords as $tier => $words): ?>
            <label class="admin-trait__pill">
                <input type="radio" name="tier" value="<?= $this->e($tier) ?>"
                       <?= ($trait?->tier ?? CreatureTrait::TIER_EARNED) === $tier ? 'checked' : '' ?>>
                <?= $this->e($words) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <fieldset class="admin-trait__choice">
        <legend class="field__label">Earned traits only: what earns it</legend>
        <?php foreach (CreatureTrait::EARN_KINDS as $kind => $words): ?>
            <label class="admin-trait__pill">
                <input type="radio" name="earn_kind" value="<?= $this->e($kind) ?>"
                       <?= ($trait?->earnKind ?? 'petted') === $kind ? 'checked' : '' ?>>
                <?= $this->e($words) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <div class="admin-trait__numbers">
        <div class="field">
            <label class="field__label" for="<?= $this->e($prefix) ?>threshold">Earned: how many</label>
            <input class="field__input" type="number" min="1" id="<?= $this->e($prefix) ?>threshold" name="earn_threshold"
                   value="<?= $this->e((string) ($trait?->earnThreshold ?? 10)) ?>">
        </div>
        <div class="field">
            <label class="field__label" for="<?= $this->e($prefix) ?>chance">Gift: chance in %</label>
            <input class="field__input" type="number" min="0" max="100" id="<?= $this->e($prefix) ?>chance" name="chance_percent"
                   value="<?= $this->e((string) ($trait?->chancePercent ?? 0)) ?>">
        </div>
        <div class="field">
            <label class="field__label" for="<?= $this->e($prefix) ?>bonus">Extra XP in % (0–25)</label>
            <input class="field__input" type="number" min="0" max="25" id="<?= $this->e($prefix) ?>bonus" name="xp_bonus_percent"
                   value="<?= $this->e((string) ($trait?->xpBonusPercent ?? 0)) ?>">
        </div>
    </div>

    <button class="btn btn--primary" type="submit"><?= $trait === null ? 'Add this trait' : 'Save' ?></button>
</form>
<?php if ($trait !== null): ?>
    <form class="admin-trait__delete" method="post" action="/admin/character/traits/<?= $this->e($id) ?>/delete"
          onsubmit="return confirm('Delete this trait? Every creature that has it loses it.');">
        <?= $this->csrf_field() ?>
        <button class="btn--quiet" type="submit">delete &ldquo;<?= $this->e($trait->name) ?>&rdquo;</button>
    </form>
<?php endif; ?>
