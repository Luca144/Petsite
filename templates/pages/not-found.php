<?php
/**
 * A simple "not found" page (HTTP 404).
 *
 * @package Felkyo\Templates
 *
 * Shown when something asked for does not exist (or the visitor is not allowed to
 * see it). It is kept generic so different pages can reuse it with their own
 * message. The friendly, fully-themed 404 comes in increment C.2.
 *
 * Variable: $message (string) — a short, plain explanation.
 */
$this->layout('layout', ['title' => 'Not found — Felkyo Creatures']);
?>

<section class="card auth-card">
    <h2>Not found</h2>
    <p><?= $this->e($message) ?></p>
    <p class="auth-alt"><a href="/">Back to the home page</a>.</p>
</section>
