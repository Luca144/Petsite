<?php
/**
 * One official notice to the player — the one-way channel's card (M2.7).
 *
 * @package Felkyo\Templates
 *
 * Inserted by layout.php above the flash for each unread notice. The
 * words come from ModerationNotice (fixed templates, never typed); the
 * appeal line names the team address from config. The only control is
 * "I've read this" — there is no reply, by design.
 *
 * Variables: $notice (ModerationNotice), $appealEmail (string).
 */
?>
<aside class="notice-card" role="status">
    <h2 class="notice-card__heading"><?= $this->e($notice->heading()) ?></h2>
    <p class="notice-card__body"><?= $this->e($notice->body()) ?></p>
    <p class="notice-card__appeal">
        If you think this was a mistake, write to us at
        <a href="mailto:<?= $this->e($appealEmail) ?>"><?= $this->e($appealEmail) ?></a>
        &mdash; a person will read it.
    </p>
    <form method="post" action="/notices/<?= $this->e((string) $notice->id) ?>/read">
        <?= $this->csrf_field() ?>
        <button class="btn btn--secondary" type="submit">I&rsquo;ve read this</button>
    </form>
</aside>
