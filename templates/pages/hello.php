<?php
/**
 * The "hello" / welcome page.
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: the temporary home page. In increment 0.3 its job is to show the
 * finished visual foundation — the themed layout plus a small, honest sample of
 * the reusable component kit (a card, tiles, buttons, a form field) so the look
 * can be checked on a real phone and desktop. Real content replaces it later.
 *
 * $appName is passed in from the route handler in public/index.php.
 */

// Wrap this page in the shared themed layout, and set the browser-tab title.
$this->layout('layout', ['title' => 'Welcome to ' . $appName]);
?>

<section class="card">
    <h2>Welcome to <?= $this->e($appName) ?></h2>
    <p>
        The foundations are in place and the room is warm. A cosy world of
        creatures to collect, grow and pet is being built here, one careful
        piece at a time.
    </p>
</section>

<hr class="rule">

<!-- A small, honest preview of the reusable building blocks every later page
     will share. These are samples of the kit, not finished features. -->
<h3>What&rsquo;s coming</h3>
<div class="tile-grid">
    <div class="tile">
        <span aria-hidden="true">&#127793;</span>
        <span class="tile__label">Creatures</span>
    </div>
    <div class="tile">
        <span aria-hidden="true">&#9832;</span>
        <span class="tile__label">Wander</span>
    </div>
    <div class="tile">
        <span aria-hidden="true">&#128717;</span>
        <span class="tile__label">The shop</span>
    </div>
    <div class="tile">
        <span aria-hidden="true">&#9829;</span>
        <span class="tile__label">Pet &amp; play</span>
    </div>
</div>

<hr class="rule">

<!-- Sample buttons and a form field, so the component styling is visible. -->
<div class="button-row">
    <a class="btn btn--primary" href="/">Look around</a>
    <a class="btn btn--secondary" href="/">Maybe later</a>
    <span class="badge"><span class="badge__dot" aria-hidden="true"></span> foundations laid</span>
</div>
