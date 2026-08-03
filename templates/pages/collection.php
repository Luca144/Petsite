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
    <div class="creature-collection">
        <?php foreach ($summaries as $summary): ?>
            <?php
            $creature = $summary['creature'];
            $species = $summary['species'];
            $slug = $species?->slug ?? '';
            // Same convention as the creature page: slug + current stage.
            $image = '/assets/creatures/' . rawurlencode($slug) . '/' . rawurlencode($summary['stage']) . '.gif';
            ?>
            <a class="creature-card" href="/creature/<?= $this->e((string) $creature->id) ?>">
                <img class="creature-card__img pixelated"
                     src="<?= $this->e($image) ?>"
                     alt="<?= $this->e($creature->name) ?>"
                     width="96" height="96">
                <span class="creature-card__name"><?= $this->e($creature->name) ?></span>
                <span class="creature-card__meta">
                    <?= $this->e($species?->name ?? 'Unknown') ?> &middot; lvl <?= $this->e((string) $summary['level']) ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<hr class="rule">

<div class="button-row">
    <a class="btn btn--secondary" href="/adopt">Adopt a creature</a>
</div>
