<?php
/**
 * The list of explorable areas.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ExplorationController::index. Each area is a tile linking to
 * its scene — wearing its own scene painting as a face when the artist has
 * chosen one (M2.5), the little tree glyph when not. Areas are panel
 * content now; new ones appear here the moment they are published.
 *
 * Variables: $areas (Felkyo\Exploration\Area[]).
 */
$this->layout('layout', ['title' => 'Explore — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->t('explore-index.where-will-you-wander', 'Where will you wander?') ?></h2>
<p><?= $this->t('explore-index.search-the-quiet-places-of', 'Search the quiet places of Felkyo. You might come home with a new friend.') ?></p>

<div class="tile-grid">
    <?php foreach ($areas as $area): ?>
        <a class="tile" href="/explore/<?= $this->e($area->slug) ?>">
            <?php if ($area->backgroundUrl() !== null): ?>
                <?php /* Decorative — the name beside it is the information. */ ?>
                <img class="tile__face" src="<?= $this->e($area->backgroundUrl()) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span aria-hidden="true">&#127795;</span>
            <?php endif; ?>
            <span><?= $this->e($area->name) ?></span>
        </a>
    <?php endforeach; ?>
</div>
