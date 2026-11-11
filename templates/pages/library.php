<?php
/**
 * The library's shelf (M5) — a book per species, or the Felkyo Gazette.
 *
 * @package Felkyo\Templates
 *
 * Rendered by LibraryController::index. Public: guests may browse it too.
 *
 * Variables: $tab ('creatures'|'gazette'), $shelf (Species[] — published,
 * alphabetical), $articles (Article[] — published, newest first),
 * $coverStage (string — the stage each species is pictured at on its spine).
 *
 * The tabs are LINKS, like the community page's: each is a real address
 * that works with scripting off, and aria-current carries both the announced
 * state and the look (community.css explains why).
 */
$this->layout('layout', ['title' => 'The library — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->t('library.the-library', 'The library') ?></h2>

<nav class="community__tabs" aria-label="Library sections">
    <a class="community__tab" href="/library?tab=creatures"<?= $tab === 'creatures' ? ' aria-current="page"' : '' ?>>
        <?= $this->t('library.creatures', 'creatures') ?>
    </a>
    <a class="community__tab" href="/library?tab=gazette"<?= $tab === 'gazette' ? ' aria-current="page"' : '' ?>>
        <?= $this->t('library.the-gazette', 'the gazette') ?>
    </a>
</nav>

<?php if ($tab === 'creatures'): ?>

    <p><?= $this->t('library.every-creature-known-to-live', 'Every creature known to live in Felkyo has a book here. Open one to see how it grows up — and what else it might turn out to be.') ?></p>

    <?php if ($shelf === []): ?>
        <p class="empty-state"><?= $this->t('library.the-shelves-are-still-empty', 'The shelves are still empty. The first books are being written.') ?></p>
    <?php else: ?>
        <ul class="library-shelf">
            <?php foreach ($shelf as $species): ?>
                <li>
                    <a class="library-shelf__book" href="/library/<?= $this->e(rawurlencode($species->slug)) ?>">
                        <?php /* The cover picture is decorative here: the species'
                                 name right below it says the same thing in words. */ ?>
                        <span class="library-shelf__cover" aria-hidden="true">
                            <img class="pixelated" src="<?= $this->e($species->spriteUrlFor($coverStage)) ?>" alt="" loading="lazy">
                        </span>
                        <span class="library-shelf__title"><?= $this->e($species->name) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php else: ?>

    <header class="gazette-masthead" aria-hidden="true">
        <span class="gazette-masthead__name"><?= $this->t('library.the-felkyo-gazette', 'The Felkyo Gazette') ?></span>
        <span class="gazette-masthead__motto"><?= $this->t('library.news-gossip-small-wonders', 'news, gossip & small wonders') ?></span>
    </header>

    <?php if ($articles === []): ?>
        <p class="empty-state"><?= $this->t('library.no-news-yet-the-presses', 'No news yet — the presses are warming up.') ?></p>
    <?php else: ?>
        <ul class="gazette-list">
            <?php foreach ($articles as $article): ?>
                <li class="gazette-list__item">
                    <a class="gazette-list__link" href="/gazette/<?= $this->e(rawurlencode($article->slug)) ?>">
                        <?php if ($article->artUrl() !== null): ?>
                            <img class="gazette-list__art" src="<?= $this->e($article->artUrl()) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <span class="gazette-list__words">
                            <span class="gazette-list__title"><?= $this->e($article->title) ?></span>
                            <?php if ($article->summary !== null && $article->summary !== ''): ?>
                                <span class="gazette-list__summary"><?= $this->e($article->summary) ?></span>
                            <?php endif; ?>
                            <?php if ($article->publishedAt !== null): ?>
                                <span class="gazette-list__date"><?= $this->e(date('j F Y', (int) strtotime($article->publishedAt))) ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php endif; ?>
