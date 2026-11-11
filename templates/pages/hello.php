<?php
/**
 * The home / welcome page.
 *
 * @package Felkyo\Templates
 *
 * For a logged-in player this greets them and lists their creatures (each links
 * to that creature's page). For a logged-out visitor it is a welcome with a small
 * preview of the reusable component kit and the ways in. Rendered by HomeController
 * (and by the 0.3 layout test, which passes only $appName — so everything here
 * copes with the logged-out case by default).
 *
 * Variables: $appName (string); $summaries (from CreatureProfileBuilder, may be
 * absent/empty). $currentUser is shared with every template by the front
 * controller.
 */
$this->layout('layout', ['title' => 'Welcome to ' . $appName]);

// Default so this template also renders for a plain guest (and in the layout test).
$summaries = $summaries ?? [];
?>

<?php if (!empty($currentUser)): ?>

    <section class="card">
        <h2><?= $this->t('hello.welcome-back', 'Welcome back, {username}', ['username' => $currentUser->username]) ?></h2>
        <p><?= $this->t('hello.here-are-the-creatures-in', 'Here are the creatures in your care.') ?></p>
    </section>

    <?= $this->insert('partials/welcome-bubble', ['welcome' => $welcome ?? null]) ?>

    <?= $this->insert('partials/latest-updates', ['updates' => $updates ?? []]) ?>

    <hr class="rule">

    <h2 class="panel-label"><?= $this->t('hello.your-creatures', 'Your creatures') ?></h2>
    <?php if ($summaries === []): ?>
        <p class="empty-state"><?= $this->t('hello.you-don-t-have-a', 'You don’t have a creature yet.') ?></p>
    <?php else: ?>
        <?php /* The same portrait cards as the collection page, on purpose. An
                 earlier version drew a bare name-tile with a seedling glyph here,
                 which meant the first thing a new player saw of their creature
                 was... not their creature. One card, one look, everywhere. */ ?>
        <div class="creature-collection">
            <?php foreach ($summaries as $summary): ?>
                <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>

    <!-- What we invite a visitor to do depends on whether they CAN sign up. On the
         deployed demo registration is closed, so inviting them to make an account
         would only lead to a "sorry, not today" page. We point them at the
         creatures instead. -->
    <?php /* The doors are INSIDE the welcome card, right under the sentence that
             explains them — not stranded after a rule at the bottom of the panel,
             where they read as furniture left behind ("take the buttons off the
             bottom of the page", and he was right). An invitation and its door
             belong in the same breath. */ ?>
    <section class="card">
        <h2><?= $this->t('hello.welcome-to', 'Welcome to {app_name}', ['app_name' => $appName]) ?></h2>
        <?php if (!empty($registrationOpen)): ?>
            <p>
                <?= $this->t('hello.a-cosy-world-of-creatures', 'A cosy world of creatures to collect, grow and pet. Make an account to meet your first one — the kettle’s on.') ?>
            </p>
        <?php else: ?>
            <p>
                <?= $this->t('hello.a-cosy-world-of-creatures-2', 'A cosy world of creatures to collect, grow and pet. Have a wander and meet the ones who already live here — the kettle’s on.') ?>
            </p>
        <?php endif; ?>

        <div class="button-row hello__doors">
            <?php if (!empty($registrationOpen)): ?>
                <a class="btn btn--primary" href="/register"><?= $this->t('hello.create-an-account', 'Create an account') ?></a>
            <?php else: ?>
                <a class="btn btn--primary" href="/browse"><?= $this->t('hello.browse-the-creatures', 'Browse the creatures') ?></a>
            <?php endif; ?>
            <a class="btn btn--secondary" href="/login"><?= $this->t('hello.log-in', 'Log in') ?></a>
        </div>
    </section>

<?php endif; ?>
