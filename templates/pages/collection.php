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
    <?php /* Never a dead end: say where creatures come from and link there. */ ?>
    <p class="empty-state">
        You don&rsquo;t have any creatures yet.
        <a href="/shop">The village store</a> has some looking for a home,
        and one may follow you back from <a href="/explore">exploring</a>.
    </p>
<?php else: ?>
    <?php /* The way to MORE creatures lives in this sentence, not as a button at
             the bottom of the page. A lone button stranded after the grid read as
             floating furniture (the Product Owner said so, and he was right) — and
             a link inside the line about your creatures is where somebody
             wondering about another one is actually looking. */ ?>
    <p class="collection-hint">
        Star one to keep it beside you on every page &mdash; and
        <a href="/shop">the village store</a> has more looking for a home.
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

