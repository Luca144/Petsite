<?php
/**
 * A list of site updates: picture left, headline, a few lines, the date.
 *
 * @package Felkyo\Templates
 *
 * Shared by the home page's "What's new" and the /updates page, so both
 * look the same. Reuses the Gazette list's styles (library.css), which
 * already put the picture on the left.
 *
 * Variable: $updates (Article[]).
 */
?>
<ul class="gazette-list">
    <?php foreach ($updates as $update): ?>
        <li class="gazette-list__item">
            <a class="gazette-list__link" href="/gazette/<?= $this->e(rawurlencode($update->slug)) ?>">
                <?php if ($update->artUrl() !== null): ?>
                    <img class="gazette-list__art" src="<?= $this->e($update->artUrl()) ?>" alt="" loading="lazy">
                <?php endif; ?>
                <span class="gazette-list__words">
                    <span class="gazette-list__title"><?= $this->e($update->title) ?></span>
                    <?php if ($update->summary !== null && $update->summary !== ''): ?>
                        <span class="gazette-list__summary"><?= $this->e($update->summary) ?></span>
                    <?php endif; ?>
                    <?php if ($update->publishedAt !== null): ?>
                        <span class="gazette-list__date"><?= $this->e(date('j F Y', (int) strtotime($update->publishedAt))) ?></span>
                    <?php endif; ?>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
