<?php
/**
 * The panel door — re-confirm the password (and code) to enter the panel.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminDoorController for staff only (the AdminGate 404s everyone
 * else before this template is reached). The code field appears only once an
 * authenticator app is connected; before that, the password alone leads to
 * the enrolment screen.
 *
 * Variables: $errors (string[]), $enrolled (bool).
 */
$this->layout('layout', ['title' => 'The panel door — Felkyo Creatures']);
?>

<section class="card auth-card admin-door">
    <h2>The panel door</h2>

    <p class="admin-door__why">
        This is the staff side of Felkyo. Confirm it&rsquo;s really you —
        the door asks again after half an hour of quiet, on purpose.
    </p>

    <?= $this->insert('partials/form-errors', ['errors' => $errors]) ?>

    <form method="post" action="/admin/door">
        <?= $this->csrf_field() ?>

        <div class="field">
            <label class="field__label" for="password">Your password</label>
            <input class="field__input" type="password" id="password" name="password"
                   autocomplete="current-password" required autofocus>
        </div>

        <?php if ($enrolled): ?>
            <div class="field">
                <label class="field__label" for="code">Code from your authenticator app</label>
                <?php /* inputmode="numeric" brings up the number pad on a phone.
                         autocomplete="one-time-code" lets browsers that catch
                         codes offer to fill it. NOT type="number" — that strips
                         leading zeros, and "034512" is a real code. */ ?>
                <input class="field__input admin-door__code" type="text" id="code" name="code"
                       inputmode="numeric" autocomplete="one-time-code"
                       placeholder="123456" required>
                <p class="field__hint">Lost the phone? A recovery code works here too.</p>
            </div>
        <?php endif; ?>

        <button class="btn btn--primary" type="submit">Open the door</button>
    </form>
</section>
