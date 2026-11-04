<?php
/**
 * A row of keepsake badges — on a creature's page (badges it earned by being
 * kept a long time) and on a player's page (special badges like "Founder").
 *
 * @package Felkyo\Templates
 *
 * Each badge shows its picture if the artist has drawn one, a gold medallion
 * if not, and ALWAYS its name in words beside it (never picture alone). The
 * description is visible text too, not a hover-only tooltip.
 *
 * Variables: $badges (list of badge arrays), $heading (string).
 */
if ($badges === []) {
    return;
}
?>
<section class="badge-shelf" aria-label="<?= $this->e($heading) ?>">
    <h3 class="badge-shelf__heading"><?= $this->e($heading) ?></h3>
    <ul class="badge-shelf__list">
        <?php foreach ($badges as $badge): ?>
            <li class="badge">
                <?php if ($badge['art_name'] !== null): ?>
                    <img class="badge__art pixelated" src="/art/<?= $this->e($badge['art_name']) ?>" alt="" width="48" height="48">
                <?php else: ?>
                    <span class="badge__medal" aria-hidden="true">&#9733;</span>
                <?php endif; ?>
                <span class="badge__words">
                    <span class="badge__name"><?= $this->e($badge['name']) ?></span>
                    <span class="badge__description"><?= $this->e($badge['description']) ?></span>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
