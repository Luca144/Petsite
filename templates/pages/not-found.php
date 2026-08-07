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
    <!-- The artist's animated 404 art. It loops on its own because it is a GIF —
         nothing needs to start it. It gets real alt text rather than being hidden,
         because it is the subject of this page, not a background flourish. -->
    <img class="not-found__art" src="/assets/art/not-found.gif"
         alt="A startled little creature tumbling through a torn hole marked 404"
         width="300" height="300">
    <h2>Nothing here but moonlight</h2>
    <p><?= $this->e($message) ?></p>
    <p class="auth-alt">
        <a href="/">Back to the home page</a> &middot; <a href="/browse">Browse creatures</a>
    </p>
</section>
