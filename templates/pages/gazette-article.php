<?php
/**
 * One Felkyo Gazette article (M5.4).
 *
 * @package Felkyo\Templates
 *
 * Rendered by LibraryController::article. Public.
 *
 * Variable: $article (Article, published).
 *
 * The body is plain text written in the panel. Every paragraph is escaped —
 * nothing the editor types is ever treated as HTML.
 */
$this->layout('layout', ['title' => $article->title . ' — The Felkyo Gazette']);
?>

<?php if ($article->isUpdate()): ?>
    <p class="library-back"><a href="/updates"><?= $this->t('gazette-article.all-updates', '← all updates') ?></a></p>
<?php else: ?>
    <p class="library-back"><a href="/library?tab=gazette"><?= $this->t('gazette-article.back-to-the-gazette', '← back to the Gazette') ?></a></p>
<?php endif; ?>

<article class="gazette-article">
    <header class="gazette-article__header">
        <p class="gazette-masthead__name gazette-article__paper" aria-hidden="true"><?= $article->isUpdate() ? 'Felkyo Updates' : 'The Felkyo Gazette' ?></p>
        <h2 class="gazette-article__title"><?= $this->e($article->title) ?></h2>
        <?php if ($article->publishedAt !== null): ?>
            <p class="gazette-list__date"><?= $this->e(date('j F Y', (int) strtotime($article->publishedAt))) ?></p>
        <?php endif; ?>
    </header>

    <?php if ($article->artUrl() !== null): ?>
        <img class="gazette-article__art" src="<?= $this->e($article->artUrl()) ?>"
             alt="<?= $this->e('Picture for: ' . $article->title) ?>">
    <?php endif; ?>

    <?php if ($article->summary !== null && $article->summary !== ''): ?>
        <p class="gazette-article__standfirst"><?= $this->e($article->summary) ?></p>
    <?php endif; ?>

    <div class="gazette-article__body">
        <?php foreach ($article->paragraphs() as $paragraph): ?>
            <p><?= $this->e($paragraph) ?></p>
        <?php endforeach; ?>
    </div>
</article>
