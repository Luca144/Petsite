<?php
/**
 * Editing one Gazette article in the panel.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminGazetteController::show. Separate small forms — words,
 * picture, publish, delete — each saving on its own, like every panel editor.
 *
 * Variable: $article (Article, draft or published).
 */
$this->layout('layout', ['title' => $article->title . ' — The Gazette — Felkyo Creatures']);
$id = (string) $article->id;
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($article->title) ?></h2>
        <p class="admin-species__statusline">
            <span class="admin-species__status admin-species__status--<?= $article->isPublished() ? 'published' : 'draft' ?>">
                <?= $article->isPublished() ? 'published' : 'draft' ?>
            </span>
        </p>
        <div class="admin-species__actions">
            <form method="post" action="/admin/gazette/<?= $this->e($id) ?>/<?= $article->isPublished() ? 'unpublish' : 'publish' ?>">
                <?= $this->csrf_field() ?>
                <button class="btn <?= $article->isPublished() ? 'btn--secondary' : 'btn--primary' ?>" type="submit">
                    <?= $article->isPublished() ? 'Back to draft' : 'Publish' ?>
                </button>
            </form>
            <?php if ($article->isPublished()): ?>
                <a class="btn btn--secondary" href="/gazette/<?= $this->e(rawurlencode($article->slug)) ?>">Read it as players do</a>
            <?php endif; ?>
        </div>
        <p><a href="/admin/gazette">&larr; all articles</a></p>
    </header>

    <div class="card">
        <h3>The words</h3>
        <form method="post" action="/admin/gazette/<?= $this->e($id) ?>/words">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="title">Headline</label>
                <input class="field__input" type="text" id="title" name="title" maxlength="120"
                       value="<?= $this->e($article->title) ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="summary">One-line summary (shown in the list, and in italics under the headline)</label>
                <input class="field__input" type="text" id="summary" name="summary" maxlength="300"
                       value="<?= $this->e((string) $article->summary) ?>">
            </div>
            <div class="field">
                <label class="field__label" for="body">The article (leave a blank line between paragraphs)</label>
                <textarea class="field__input" id="body" name="body" rows="14"><?= $this->e((string) $article->body) ?></textarea>
            </div>
            <button class="btn btn--primary" type="submit">Save</button>
        </form>
    </div>

    <div class="card admin-stage">
        <h3>Picture</h3>
        <div class="admin-stage__row">
            <span class="admin-stage__art">
                <?php if ($article->artUrl() !== null): ?>
                    <img src="<?= $this->e($article->artUrl()) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="admin-stage__none">none yet</span>
                <?php endif; ?>
            </span>
            <a class="btn btn--secondary" href="/admin/gazette/<?= $this->e($id) ?>/picture">Choose a picture</a>
        </div>
        <p class="field__hint">Library pictures, portraits, backgrounds and cards can all go here.</p>
    </div>

    <div class="card">
        <form method="post" action="/admin/gazette/<?= $this->e($id) ?>/delete"
              onsubmit="return confirm('Delete this article for good?');">
            <?= $this->csrf_field() ?>
            <button class="btn--quiet" type="submit">delete this article</button>
        </form>
    </div>
</section>
