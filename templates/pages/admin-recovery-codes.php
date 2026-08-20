<?php
/**
 * The one-time showing of a staff account's recovery codes.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminEnrolController exactly once, right after enrolment
 * succeeds. The codes are stored hashed, so the site itself can never show
 * them again — this page says so plainly and asks the person to keep them
 * somewhere safe before moving on.
 *
 * Variables: $recoveryCodes (string[]).
 */
$this->layout('layout', ['title' => 'Your recovery codes — Felkyo Creatures']);
?>

<section class="card auth-card admin-recovery">
    <h2>Your app is connected</h2>

    <p>
        One last thing, and it matters: these are your <strong>recovery
        codes</strong>. If you ever lose your phone, one of these opens the
        panel door in place of the app &mdash; each works exactly once.
    </p>

    <p class="admin-recovery__warning">
        <strong>This page is the only time they will ever be shown.</strong>
        Write them down or save them somewhere safe (not in a note on the same
        phone), then carry on.
    </p>

    <ul class="admin-recovery__codes" aria-label="Your recovery codes">
        <?php foreach ($recoveryCodes as $code): ?>
            <li><?= $this->e($code) ?></li>
        <?php endforeach; ?>
    </ul>

    <a class="btn btn--primary" href="/admin">I&rsquo;ve saved them — into the panel</a>
</section>
