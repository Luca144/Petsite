<?php
/**
 * One exploration area's scene, with clickable spots to search.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ExplorationController::show. The whole scene is one form: clicking
 * any spot submits it, which searches the area once (see ExplorationController).
 * When the visit's searches are used up, the spots are replaced by a note.
 *
 * The scene background is a themed placeholder; real area art can replace it later.
 *
 * Variables:
 *   $areaSlug (string), $area (the area's config array), $remaining (int),
 *   $flash (string|null)
 */
$this->layout('layout', ['title' => $area['name'] . ' — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->e($area['name']) ?></h2>
<p><?= $this->e($area['description']) ?></p>
<p class="explore-clicks">Searches left this visit: <b><?= $this->e((string) $remaining) ?></b></p>

<?php if ($remaining > 0): ?>
    <form method="post" action="/explore/<?= $this->e($areaSlug) ?>" class="explore-scene">
        <?= $this->csrf_field() ?>
        <?php foreach ($area['spots'] as $spot): ?>
            <!-- Each spot is a submit button positioned on the scene. Its position
                 is passed as CSS custom properties (--x/--y), which the stylesheet
                 uses — so no styling rules live inline, only the data values. -->
            <button type="submit" class="explore-spot"
                    style="--x: <?= $this->e((string) $spot['x']) ?>%; --y: <?= $this->e((string) $spot['y']) ?>%;"
                    aria-label="Search this spot">
                <span aria-hidden="true">&#10022;</span>
            </button>
        <?php endforeach; ?>
    </form>
<?php else: ?>
    <div class="explore-scene explore-scene--spent">
        <p class="empty-state">You&rsquo;ve searched all you can here for now. Come back a little later.</p>
    </div>
<?php endif; ?>

<p class="auth-alt">
    <a href="/explore">Back to areas</a> &middot; <a href="/creatures">Your creatures</a>
</p>
