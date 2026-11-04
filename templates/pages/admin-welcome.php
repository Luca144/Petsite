<?php
/**
 * The panel's editor for the first-run welcome (M7): Ruinily's lines.
 *
 * @package Felkyo\Templates
 *
 * Rendered by WelcomeController::edit. Variable: $lines (string).
 */
$this->layout('layout', ['title' => 'The welcome — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>The welcome</h2>
        <p>
            What the shopkeeper says to a new player on the home page, one step at a time. Players can
            always click &ldquo;don't show me this again&rdquo;, and once they have, it never comes back.
        </p>
        <p><a href="/admin">&larr; back to the panel</a></p>
    </header>

    <form class="card" method="post" action="/admin/welcome">
        <?= $this->csrf_field() ?>
        <label class="field__label" for="lines">
            One step per line. To add a button, put a | then the page (starting with /) then another | and the button's words &mdash;
            for example: <code>Spend them in my shop! | /shop | Visit the shop</code>
        </label>
        <textarea class="field__input" id="lines" name="lines" rows="12"><?= $this->e($lines) ?></textarea>
        <button class="btn btn--primary" type="submit">Save her lines</button>
    </form>
</section>
