<?php
/**
 * The panel's Gazette desk: every article, and a way to start a new one.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminGazetteController::index.
 * Variable: $articles (Article[], drafts included, newest first).
 */
$this->layout('layout', ['title' => 'The Gazette — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>The Felkyo Gazette</h2>
        <p>News and stories about the people of Felkyo. Published pieces appear in the library, under &ldquo;the gazette&rdquo;, newest first.</p>
        <p><a href="/admin">&larr; back to the panel</a> &middot; <a href="/library?tab=gazette">see the Gazette as players do</a></p>
    </header>

    <div class="card">
        <h3>Start a new article</h3>
        <form method="post" action="/admin/gazette/create">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="title">Headline</label>
                <input class="field__input" type="text" id="title" name="title" maxlength="120" required>
            </div>
            <button class="btn btn--primary" type="submit">Start writing</button>
        </form>
    </div>

    <div class="card">
        <h3>All articles</h3>
        <?php if ($articles === []): ?>
            <p class="empty-state">Nothing written yet.</p>
        <?php else: ?>
            <ul class="admin-gazette__list">
                <?php foreach ($articles as $article): ?>
                    <li class="admin-gazette__row">
                        <span class="admin-species__status admin-species__status--<?= $article->isPublished() ? 'published' : 'draft' ?>">
                            <?= $article->isPublished() ? 'published' : 'draft' ?>
                        </span>
                        <a href="/admin/gazette/<?= $this->e((string) $article->id) ?>"><?= $this->e($article->title) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
