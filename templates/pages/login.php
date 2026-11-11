<?php
/**
 * The "log in" page.
 *
 * @package Felkyo\Templates
 *
 * Shows the login form. On a failed attempt it re-renders with one generic
 * message (we never say whether it was the username or the password that was
 * wrong) and keeps the typed username. Rendered by LoginController.
 *
 * Variables: $errors (string[]), $username (string).
 */
$this->layout('layout', ['title' => 'Log in — Felkyo Creatures']);
?>

<section class="card auth-card">
    <h2><?= $this->t('login.log-in', 'Log in') ?></h2>

    <?= $this->insert('partials/form-errors', ['errors' => $errors]) ?>

    <form method="post" action="/login">
        <?= $this->csrf_field() ?>

        <div class="field">
            <label class="field__label" for="username"><?= $this->t('login.username', 'Username') ?></label>
            <input class="field__input" type="text" id="username" name="username"
                   value="<?= $this->e($username) ?>" autocomplete="username" required>
        </div>

        <div class="field">
            <label class="field__label" for="password"><?= $this->t('login.password', 'Password') ?></label>
            <div class="field__password-wrapper">
                <input class="field__input" type="password" id="password" name="password"
                       autocomplete="current-password" required>
                <label class="field__show-password">
                    <input type="checkbox" class="field__show-password-checkbox" id="show-password">
                    <span><?= $this->t('login.show', 'Show') ?></span>
                </label>
            </div>
        </div>

        <button class="btn btn--primary" type="submit"><?= $this->t('login.log-in-2', 'Log in') ?></button>
    </form>

    <?php if (!empty($registrationOpen)): ?>
        <!-- Only offered when sign-ups are actually open — see layout.php. -->
        <p class="auth-alt"><?= $this->t('login.new-here', 'New here?') ?> <a href="/register"><?= $this->t('login.create-an-account', 'Create an account') ?></a>.</p>
    <?php endif; ?>
</section>
