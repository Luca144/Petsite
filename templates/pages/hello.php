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
 * Variables: $appName (string); $creatures (Creature[], may be absent/empty).
 * $currentUser is shared with every template by the front controller.
 */
$this->layout('layout', ['title' => 'Welcome to ' . $appName]);

// Default so this template also renders for a plain guest (and in the layout test).
$creatures = $creatures ?? [];
?>

<?php if (!empty($currentUser)): ?>

    <section class="card">
        <h2>Welcome back, <?= $this->e($currentUser->username) ?></h2>
        <p>Here are the creatures in your care.</p>
    </section>

    <hr class="rule">

    <h2 class="panel-label">Your creatures</h2>
    <?php if ($creatures === []): ?>
        <p class="empty-state">You don&rsquo;t have a creature yet.</p>
    <?php else: ?>
        <div class="tile-grid">
            <?php foreach ($creatures as $creature): ?>
                <a class="tile" href="/creature/<?= $this->e((string) $creature->id) ?>">
                    <span aria-hidden="true">&#127793;</span>
                    <span><?= $this->e($creature->name) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>

    <!-- What we invite a visitor to do depends on whether they CAN sign up. On the
         deployed demo registration is closed, so inviting them to make an account
         would only lead to a "sorry, not today" page. We point them at the
         creatures instead. -->
    <section class="card">
        <h2>Welcome to <?= $this->e($appName) ?></h2>
        <?php if (!empty($registrationOpen)): ?>
            <p>
                A cosy world of creatures to collect, grow and pet. Make an account to
                meet your first one &mdash; the kettle&rsquo;s on.
            </p>
        <?php else: ?>
            <p>
                A cosy world of creatures to collect, grow and pet. Have a wander and
                meet the ones who already live here &mdash; the kettle&rsquo;s on.
            </p>
        <?php endif; ?>
    </section>

    <hr class="rule">

    <div class="button-row">
        <?php if (!empty($registrationOpen)): ?>
            <a class="btn btn--primary" href="/register">Create an account</a>
        <?php else: ?>
            <a class="btn btn--primary" href="/browse">Browse the creatures</a>
        <?php endif; ?>
        <a class="btn btn--secondary" href="/login">Log in</a>
    </div>

<?php endif; ?>
