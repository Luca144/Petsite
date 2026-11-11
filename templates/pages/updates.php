<?php
/**
 * Every site update, newest first — Felkyo's timeline (Skerro, 2026-08-30:
 * "it's how I usually check a site's timeline and how old it is").
 *
 * @package Felkyo\Templates
 *
 * Rendered by LibraryController::updates. Public.
 * Variable: $updates (Article[]).
 */
$this->layout('layout', ['title' => 'Updates — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->t('updates.updates', 'Updates') ?></h2>
<p><?= $this->t('updates.everything-new-in-felkyo-newest', 'Everything new in Felkyo, newest first.') ?></p>

<?php if ($updates === []): ?>
    <p class="empty-state"><?= $this->t('updates.no-updates-yet-the-first', 'No updates yet — the first one is on its way.') ?></p>
<?php else: ?>
    <?= $this->insert('partials/update-list', ['updates' => $updates]) ?>
<?php endif; ?>
