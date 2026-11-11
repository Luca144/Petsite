<?php
/**
 * The form for changing your own page.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ProfileController::edit(). Variables: $profile, $avatars (list of
 * ['key','name','imagePath']), $summaries (every creature they own),
 * $featuredIds (int[]), $maxAboutLength, $maxFeatured, $flash.
 *
 * NO DROPDOWNS ANYWHERE (CLAUDE.md section 8). Avatars are pictures, so they are
 * a grid of tappable tiles backed by real radio buttons; featured creatures are
 * the same idea with checkboxes. Radios and checkboxes are used because they are
 * what browsers, keyboards and screen readers already understand — the styling
 * sits on top and never replaces them.
 */
$this->layout('layout', ['title' => 'Change your page — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->t('profile-edit.change-your-page', 'Change your page') ?></h2>

<p class="profile-edit__intro">
    <?= $this->t('profile-edit.everything-here-is-visible-to', 'Everything here is visible to anyone who visits you.') ?>
    <a href="/player/<?= $this->e(rawurlencode($profile->username)) ?>"><?= $this->t('profile-edit.see-your-page-as-others', 'See your page as others see it') ?></a>
</p>

<form class="profile-form" method="post" action="/profile">
    <?= $this->csrf_field() ?>

    <fieldset class="profile-form__section">
        <legend class="panel-label"><?= $this->t('profile-edit.your-avatar', 'Your avatar') ?></legend>

        <div class="avatar-grid">
            <?php foreach ($avatars as $avatar): ?>
                <label class="avatar-choice">
                    <input class="avatar-choice__input"
                           type="radio"
                           name="avatar_key"
                           value="<?= $this->e($avatar['key']) ?>"
                           <?= $avatar['key'] === $profile->avatarKey ? 'checked' : '' ?>>
                    <img class="avatar-choice__img"
                         src="<?= $this->e($avatar['imagePath']) ?>"
                         alt="" width="72" height="72">
                    <span class="avatar-choice__name"><?= $this->e($avatar['name']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php if (count($avatars) === 1): ?>
            <p class="profile-form__note">
                <?= $this->t('profile-edit.more-avatars-are-on-their', 'More avatars are on their way — Skerro is drawing them.') ?>
            </p>
        <?php endif; ?>
    </fieldset>

    <fieldset class="profile-form__section">
        <legend class="panel-label"><?= $this->t('profile-edit.about-you', 'About you') ?></legend>

        <label class="profile-form__label" for="about">
            <?= $this->t('profile-edit.a-few-words-for-anyone', 'A few words for anyone who visits.') ?>
        </label>
        <textarea class="profile-form__textarea"
                  id="about"
                  name="about"
                  rows="4"
                  maxlength="<?= $this->e((string) $maxAboutLength) ?>"
                  aria-describedby="about-help"><?= $this->e($profile->about ?? '') ?></textarea>

        <?php /* The limit is stated in words as well as enforced by maxlength,
                 because a box that silently stops accepting letters is baffling. */ ?>
        <p class="profile-form__help" id="about-help">
            <?= $this->t('profile-edit.up-to-characters-please-don', 'Up to {max_about_length} characters. Please don’t include links, or ways to contact you outside Felkyo.', ['max_about_length' => (string) $maxAboutLength]) ?>
        </p>
    </fieldset>

    <fieldset class="profile-form__section">
        <legend class="panel-label"><?= $this->t('profile-edit.being-found', 'Being found') ?></legend>

        <label class="findable-choice">
            <input class="findable-choice__input"
                   type="checkbox"
                   name="is_findable"
                   value="yes"
                   <?= $profile->isFindable ? 'checked' : '' ?>>
            <span>
                <span class="findable-choice__title"><?= $this->t('profile-edit.let-other-players-find-me', 'Let other players find me by name') ?></span>
                <span class="findable-choice__note">
                    <?= $this->t('profile-edit.if-you-turn-this-off', 'If you turn this off you won’t appear in searches. Your page still exists, and anyone who already knows your name can still visit — a bit like an unlisted phone number.') ?>
                </span>
            </span>
        </label>
    </fieldset>

    <fieldset class="profile-form__section">
        <legend class="panel-label"><?= $this->t('profile-edit.creatures-to-show-off', 'Creatures to show off') ?></legend>

        <?php if (empty($summaries)): ?>
            <p class="empty-state">
                <?= $this->t('profile-edit.you-don-t-have-any', 'You don’t have any creatures yet. Once you do, you can choose which ones appear at the top of your page.') ?>
            </p>
        <?php else: ?>
            <p class="profile-form__help" id="featured-help">
                <?= $this->t('profile-edit.choose-up-to-if-you', 'Choose up to {max_featured}. If you choose none, your newest creatures are shown instead. Only public creatures appear on your page.', ['max_featured' => (string) $maxFeatured]) ?>
            </p>

            <div class="feature-grid" role="group" aria-describedby="featured-help">
                <?php foreach ($summaries as $summary): ?>
                    <?php $creature = $summary['creature']; ?>
                    <label class="feature-choice">
                        <input class="feature-choice__input"
                               type="checkbox"
                               name="featured[]"
                               value="<?= $this->e((string) $creature->id) ?>"
                               <?= in_array($creature->id, $featuredIds, true) ? 'checked' : '' ?>>
                        <span class="feature-choice__name"><?= $this->e($creature->name) ?></span>
                        <?php /* The word appears when the box is ticked (CSS shows
                                 it via :has(:checked)). Sighted players get it in
                                 words, not only as a gold border — never colour
                                 alone. Screen readers already hear the checkbox
                                 state itself, so this stays decorative for them. */ ?>
                        <span class="feature-choice__chosen" aria-hidden="true"><?= $this->t('profile-edit.chosen', '✓ chosen') ?></span>
                        <?php if (!$creature->isPublic): ?>
                            <?php /* Said in words, not only by a colour or an icon —
                                     otherwise this is invisible to some players. */ ?>
                            <span class="feature-choice__private"><?= $this->t('profile-edit.private-won-t-show', 'private · won’t show') ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>

    <div class="button-row">
        <button class="btn btn--primary" type="submit"><?= $this->t('profile-edit.save-my-page', 'Save my page') ?></button>
        <a class="btn btn--secondary" href="/player/<?= $this->e(rawurlencode($profile->username)) ?>">
            <?= $this->t('profile-edit.view-my-page', 'View my page') ?>
        </a>
    </div>
</form>
