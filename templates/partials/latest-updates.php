<?php
/**
 * The newest site updates on the home page (Skerro, 2026-08-30): a picture
 * on the left, the headline and a few lines, and a way to the full list.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/hello.php between the welcome and "Your creatures".
 * Shows nothing at all while no update has been published.
 *
 * Variable: $updates (Article[], newest first, at most two).
 */
if ($updates === []) {
    return;
}
?>
<section class="latest-updates" aria-labelledby="latest-updates">
    <h2 id="latest-updates" class="panel-label"><?= $this->t('latest-updates.what-s-new', 'What\'s new') ?></h2>
    <?= $this->insert('partials/update-list', ['updates' => $updates]) ?>
    <p class="latest-updates__all"><a href="/updates"><?= $this->t('latest-updates.all-updates', 'All updates →') ?></a></p>
</section>
