<?php
/**
 * Second-factor enrolment — connect an authenticator app to a staff account.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminEnrolController, only for staff whose password was just
 * confirmed at the door and who have no app connected yet. Shows the secret
 * to type into the app and asks one current code back, which proves the app
 * really holds the secret before anything is saved.
 *
 * Variables: $errors (string[]), $secret (string), $provisioningUri (string).
 */
$this->layout('layout', ['title' => 'Set up your second factor — Felkyo Creatures']);
?>

<section class="card auth-card admin-enrol">
    <h2>One more lock on the door</h2>

    <p>
        Staff accounts open everything, so they get a second lock: a code from
        an app on your phone, asked for at the panel door. Any authenticator
        app works — Aegis, FreeOTP, Google&nbsp;Authenticator, and others.
    </p>

    <?= $this->insert('partials/form-errors', ['errors' => $errors]) ?>

    <ol class="admin-enrol__steps">
        <li>
            In the app, choose <strong>add an account by entering a key</strong>
            (sometimes called &ldquo;manual entry&rdquo;), and type this in:
            <?php /* The secret is intentionally NOT hidden or truncated — the
                     person must copy it exactly, and this page only exists
                     behind their password. Space Mono via the CSS keeps the
                     characters unmistakable (0 vs O). */ ?>
            <p class="admin-enrol__secret" aria-label="Your secret key"><?= $this->e($secret) ?></p>
            <p class="field__hint">
                If the app asks: the account is time-based, 6 digits. Some apps
                accept this address pasted whole instead:<br>
                <span class="admin-enrol__uri"><?= $this->e($provisioningUri) ?></span>
            </p>
        </li>
        <li>
            The app now shows a 6-digit code that changes every half minute.
            Type the current one here to prove the app is set up right:
            <form method="post" action="/admin/enrol">
                <?= $this->csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="code">Code from the app</label>
                    <input class="field__input admin-door__code" type="text" id="code" name="code"
                           inputmode="numeric" autocomplete="one-time-code"
                           placeholder="123456" required>
                </div>
                <button class="btn btn--primary" type="submit">Connect the app</button>
            </form>
        </li>
    </ol>
</section>
