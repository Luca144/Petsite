<?php
/**
 * The words an owner attaches to their creature: its name and its bio.
 *
 * @package Felkyo\Templates
 *
 * Insert it with:
 *   $this->insert('partials/creature-words', [
 *       'creature' => $creature, 'nameMaxLength' => $nameMaxLength,
 *   ]);
 *
 * OWNER ONLY — the caller decides that. CreatureRenameController and
 * BioController are what actually refuse anybody else, so the decision never
 * rests on a template remembering to hide something.
 *
 * WHY THE TWO ARE TOGETHER: they are the same act. A name and a bio are both text
 * the owner chooses and other players read, both go through the same blocked-word
 * check, and both are the only places on this page where somebody can put words
 * of their own in front of somebody else. Keeping them side by side is also why
 * the site has one answer to "where do I change what my creature says" rather
 * than two.
 */
?>
<form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/rename" class="bio-form">
    <?= $this->csrf_field() ?>
    <label class="field__label" for="creature-name">Rename <?= $this->e($creature->name) ?></label>
    <?php /* Pre-filled with the current name, so the common case — a small
             change, or fixing a typo — is a small edit rather than a retype from
             nothing. */ ?>
    <input class="field__input" type="text" id="creature-name" name="name"
           value="<?= $this->e($creature->name) ?>"
           maxlength="<?= $this->e((string) $nameMaxLength) ?>"
           required
           aria-describedby="creature-name-hint">
    <span class="field__hint" id="creature-name-hint">
        Up to <?= $this->e((string) $nameMaxLength) ?> characters.
        You can change this whenever you like.
    </span>
    <button class="btn btn--secondary" type="submit">Save name</button>
</form>

<form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/bio" class="bio-form">
    <?= $this->csrf_field() ?>
    <label class="field__label" for="bio">Edit bio</label>
    <textarea class="field__textarea" id="bio" name="bio" maxlength="500"
              placeholder="Tell everyone a little about <?= $this->e($creature->name) ?>&hellip;"><?= $this->e($creature->bio ?? '') ?></textarea>
    <button class="btn btn--secondary" type="submit">Save bio</button>
</form>
