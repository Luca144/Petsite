<?php
/**
 * Shown when public registration is switched off.
 *
 * @package Felkyo\Templates
 *
 * The deployed Felkyo is a closed demo: it runs on a handful of seeded accounts,
 * has no real users, and holds no real personal data. So sign-ups are turned off
 * there (config `app.registration_open`), and anyone who reaches /register — by
 * bookmark, by link, or by typing it — lands here instead.
 *
 * The tone is deliberately warm rather than blunt: this is not an error the
 * visitor caused, so it should not read like one.
 */
$this->layout('layout', ['title' => 'Sign-ups are closed — Felkyo Creatures']);
?>

<section class="card auth-card">
    <h2><?= $this->t('registration-closed.the-door-is-closed-for', 'The door is closed for now') ?></h2>
    <p>
        <?= $this->t('registration-closed.felkyo-is-running-as-a', 'Felkyo is running as a small demo at the moment, so new accounts aren’t being made. Nothing has gone wrong — there just isn’t a way in from here today.') ?>
    </p>
    <p>
        <?= $this->t('registration-closed.you-can-still-wander-around', 'You can still wander around and meet the creatures who already live here.') ?>
    </p>
    <p class="auth-alt">
        <a href="/browse"><?= $this->t('registration-closed.browse-the-creatures', 'Browse the creatures') ?></a> &middot;
        <a href="/login"><?= $this->t('registration-closed.log-in', 'Log in') ?></a> &middot;
        <a href="/"><?= $this->t('registration-closed.back-to-the-home-page', 'Back to the home page') ?></a>
    </p>
</section>
