<?php
/**
 * A friendly, themed "not found" page (HTTP 404).
 *
 * @package Felkyo\Templates
 *
 * Shown when a page or creature does not exist (or the visitor is not allowed to
 * see it). Kept generic so different places can reuse it with their own message.
 *
 * Variable: $message (string) — a short, plain explanation.
 */
$this->layout('layout', ['title' => 'Not found — Felkyo Creatures']);
?>

<section class="card auth-card not-found">
    <p class="not-found__moon" aria-hidden="true">&#9790;</p>
    <h2>Nothing here but moonlight</h2>
    <p><?= $this->e($message) ?></p>
    <p class="auth-alt">
        <a href="/">Back to the home page</a> &middot; <a href="/browse">Browse creatures</a>
    </p>
</section>
