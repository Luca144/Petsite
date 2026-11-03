<?php
/**
 * The panel's list of themes (M4).
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminThemesController::index. Variable: $themes (Theme[]).
 */
$this->layout('layout', ['title' => 'Themes — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Themes</h2>
        <p>Colour sets players can choose on their <a href="/themes">theme page</a>. The editor checks readability while you pick colours, and won't publish a theme that's hard to read.</p>
        <p><a href="/admin">&larr; back to the panel</a></p>
    </header>

    <div class="card">
        <h3>Start a new theme</h3>
        <form method="post" action="/admin/themes/create">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="theme-name">Its name</label>
                <input class="field__input" type="text" id="theme-name" name="name" maxlength="40" required>
            </div>
            <button class="btn btn--primary" type="submit">Start it as a draft</button>
        </form>
    </div>

    <div class="card">
        <h3>All themes</h3>
        <ul class="admin-gazette__list">
            <?php foreach ($themes as $theme): ?>
                <li class="admin-gazette__row">
                    <span class="admin-species__status admin-species__status--<?= $theme->isPublished() ? 'published' : 'draft' ?>">
                        <?= $theme->isPublished() ? 'published' : 'draft' ?>
                    </span>
                    <a href="/admin/themes/<?= $this->e((string) $theme->id) ?>"><?= $this->e($theme->name) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
