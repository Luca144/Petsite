<?php
/**
 * "Something broken?" — the bug report form.
 *
 * @package Felkyo\Templates
 *
 * Rendered by BugReportController (logged-in players only). Reached from the
 * footer link on every page, which passes the page's address along, so the
 * player only has to say WHAT happened — never where, or on what device.
 *
 * Variables: $from (the page path it's about), $description (their words,
 * kept if the form comes back with a problem), $errors (what to fix, if any).
 *
 * Text only, no screenshots: uploads are the riskiest thing a site can
 * accept, and many players are children.
 */
$this->layout('layout', ['title' => 'Something broken? — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->t('bug-report.something-broken', 'Something broken?') ?></h2>

<p>
    <?= $this->t('bug-report.thank-you-for-telling-us', 'Thank you for telling us! Say what you did and what went wrong — for example “I pressed Feed and nothing happened” or “the button is cut off on my phone”.') ?>
</p>

<form method="post" action="/bug-report" class="card bug-report">
    <?= $this->csrf_field() ?>
    <input type="hidden" name="from" value="<?= $this->e($from) ?>">
    <?php /* Filled in by bug-report.js with the window's width, which is what
             most layout bugs depend on. Without JavaScript it simply stays empty. */ ?>
    <input type="hidden" name="screen_width" value="" data-screen-width>

    <?= $this->insert('partials/form-errors', ['errors' => $errors ?? []]) ?>

    <p class="bug-report__page">
        <?= $this->t('bug-report.about-the-page', 'About the page:') ?> <code><?= $this->e($from) ?></code>
    </p>

    <label class="profile-form__label" for="bug-description"><?= $this->t('bug-report.what-happened', 'What happened?') ?></label>
    <textarea class="profile-form__textarea" id="bug-description" name="description"
              rows="6" maxlength="2000" required aria-describedby="bug-help"><?= $this->e($description) ?></textarea>
    <p class="profile-form__help" id="bug-help">
        <?= $this->t('bug-report.up-to-2-000-characters', 'Up to 2,000 characters. Please don’t write passwords or anything private — your browser’s name and window size are sent along automatically.') ?>
    </p>

    <div class="button-row">
        <button class="btn btn--primary" type="submit"><?= $this->t('bug-report.send-bug-report', 'Send bug report') ?></button>
        <a class="btn btn--secondary" href="<?= $this->e($from) ?>"><?= $this->t('bug-report.never-mind-go-back', 'Never mind, go back') ?></a>
    </div>
</form>

<script src="<?= \Felkyo\Http\AssetUrl::versioned('/js/bug-report.js') ?>" defer></script>
