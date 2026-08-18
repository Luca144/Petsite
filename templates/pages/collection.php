<?php
/**
 * The player's collection — a grid of all their creatures.
 *
 * @package Felkyo\Templates
 *
 * Rendered by CollectionController. Each card shows a small portrait, the
 * creature's name, its species and level, and links to that creature's page.
 *
 * Variable: $summaries — an array of ['creature' => Creature, 'species' => ?Species,
 * 'level' => int, 'stage' => string].
 */
$this->layout('layout', ['title' => 'Your creatures — Felkyo Creatures']);
?>

<h2 class="panel-label">Your creatures</h2>

<?php if (empty($summaries)): ?>
    <p class="empty-state">You don&rsquo;t have any creatures yet. Try adopting one!</p>
<?php else: ?>
    <p class="collection-hint">
        Star one to keep it beside you on every page.
    </p>

    <div class="creature-collection">
        <?php foreach ($summaries as $summary): ?>
            <?php /* Card and star are SIBLINGS, not nested. The card is a link to
                     the creature, and the star is a form that changes something —
                     a form inside a link is invalid HTML, and browsers resolve it
                     in ways nobody should have to predict. They are wrapped
                     together so they read and lay out as one tile. */ ?>
            <div class="creature-collection__item">
                <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
                <?= $this->insert('partials/favourite-star', [
                    'creature' => $summary['creature'],
                    'from' => 'collection',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<hr class="rule">

<div class="button-row">
    <a class="btn btn--secondary" href="/adopt">Adopt a creature</a>
</div>
