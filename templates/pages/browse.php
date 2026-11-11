<?php
/**
 * The public "browse" page — recently met creatures from around Felkyo.
 *
 * @package Felkyo\Templates
 *
 * Rendered by BrowseController. Anyone can see this (logged in or not). Each card
 * links to that creature's page. Only public creatures appear here.
 *
 * Variable: $summaries — entries from CreatureProfileBuilder::summariesFor().
 */
$this->layout('layout', ['title' => 'Browse creatures — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->t('browse.recently-met-creatures', 'Recently met creatures') ?></h2>
<p><?= $this->t('browse.a-few-of-the-creatures', 'A few of the creatures making their home in Felkyo right now. Say hello — you can pet any of them.') ?></p>

<?php if (empty($summaries)): ?>
    <p class="empty-state"><?= $this->t('browse.no-creatures-to-show-yet', 'No creatures to show yet. Be the first!') ?></p>
<?php else: ?>
    <div class="creature-collection">
        <?php foreach ($summaries as $summary): ?>
            <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
