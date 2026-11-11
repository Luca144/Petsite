<?php
/**
 * The "create an account" page.
 *
 * @package Felkyo\Templates
 *
 * Shows the registration form. On an error, it re-renders with the messages (via
 * the form-errors partial) and keeps the username and email the person typed
 * (never the password). Rendered by RegisterController.
 *
 * Variables: $errors (string[]), $username (string), $email (string).
 */
$this->layout('layout', ['title' => 'Create an account — Felkyo Creatures']);
?>

<section class="card auth-card">
    <h2><?= $this->t('register.create-an-account', 'Create an account') ?></h2>

    <?= $this->insert('partials/form-errors', ['errors' => $errors]) ?>

    <form method="post" action="/register">
        <!-- The CSRF hidden field is added automatically by this helper; every
             form on the site includes it (CLAUDE.md section 6). -->
        <?= $this->csrf_field() ?>

        <div class="field">
            <label class="field__label" for="username"><?= $this->t('register.username', 'Username') ?></label>
            <input class="field__input" type="text" id="username" name="username"
                   value="<?= $this->e($username) ?>" autocomplete="username"
                   minlength="3" maxlength="30" required>
            <span class="field__hint"><?= $this->t('register.3-30-characters-letters-numbers', '3–30 characters: letters, numbers, _ and -') ?></span>
        </div>

        <div class="field">
            <label class="field__label" for="email"><?= $this->t('register.email', 'Email') ?></label>
            <input class="field__input" type="email" id="email" name="email"
                   value="<?= $this->e($email) ?>" autocomplete="email"
                   maxlength="255" required>
        </div>

        <div class="field">
            <label class="field__label" for="password"><?= $this->t('register.password', 'Password') ?></label>
            <div class="field__password-wrapper">
                <input class="field__input" type="password" id="password" name="password"
                       autocomplete="new-password" minlength="8" maxlength="72" required>
                <label class="field__show-password">
                    <input type="checkbox" class="field__show-password-checkbox" id="show-password">
                    <span><?= $this->t('register.show', 'Show') ?></span>
                </label>
            </div>
            <span class="field__hint"><?= $this->t('register.at-least-8-characters', 'At least 8 characters') ?></span>
        </div>

        <button class="btn btn--primary" type="submit"><?= $this->t('register.create-account', 'Create account') ?></button>
    </form>

    <p class="auth-alt"><?= $this->t('register.already-have-an-account', 'Already have an account?') ?> <a href="/login"><?= $this->t('register.log-in', 'Log in') ?></a>.</p>
</section>
