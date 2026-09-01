<?php
/**
 * The stats page — the game's health, at a glance (M2.9).
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminStatsController (Artist role). Aggregate only: the
 * only names on this page are content names — species and areas. It
 * replaces the manually kept spreadsheet, and it is the artist's
 * marketing information.
 *
 * Variables: $stats (from SiteStatsService::gather).
 */
$this->layout('layout', ['title' => 'Stats — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Stats</h2>
        <p>
            The game&rsquo;s health, in aggregate — no number here is about
            any one player, on purpose. &ldquo;This week&rdquo; means the
            last seven days.
        </p>
    </header>

    <div class="card">
        <h3>People &amp; creatures</h3>
        <ul class="admin-stats__tiles">
            <li class="admin-stats__tile">
                <b><?= (int) $stats['players']['total'] ?></b>
                <span>player<?= $stats['players']['total'] === 1 ? '' : 's' ?> all told</span>
            </li>
            <li class="admin-stats__tile">
                <b><?= (int) $stats['players']['newThisWeek'] ?></b>
                <span>joined this week</span>
            </li>
            <li class="admin-stats__tile">
                <b><?= (int) $stats['players']['activeThisWeek'] ?></b>
                <span>logged in this week</span>
            </li>
            <li class="admin-stats__tile">
                <b><?= (int) $stats['creatures']['total'] ?></b>
                <span>creatures in the world</span>
            </li>
            <li class="admin-stats__tile">
                <b><?= (int) $stats['creatures']['newThisWeek'] ?></b>
                <span>adopted this week</span>
            </li>
        </ul>
    </div>

    <div class="card">
        <h3>Who gets adopted</h3>
        <p class="field__hint">
            Every species, most-kept first — a species nobody picks is a
            finding, not a gap.
        </p>
        <ul class="admin-stats__bars">
            <?php foreach ($stats['bySpecies'] as $species): ?>
                <li>
                    <span class="admin-stats__bar-name">
                        <?= $this->e($species['name']) ?>
                        <?php if ($species['status'] !== 'published'): ?>
                            <span class="admin-audit__subject">draft</span>
                        <?php endif; ?>
                    </span>
                    <b><?= (int) $species['count'] ?></b>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="card">
        <h3>Care, this week</h3>
        <ul class="admin-stats__tiles">
            <li class="admin-stats__tile">
                <b><?= (int) $stats['care']['petsThisWeek'] ?></b>
                <span>pets given</span>
            </li>
            <li class="admin-stats__tile">
                <b><?= (int) $stats['care']['guestbookThisWeek'] ?></b>
                <span>guestbooks signed</span>
            </li>
        </ul>
    </div>

    <div class="card">
        <h3>The economy</h3>
        <ul class="admin-stats__tiles">
            <li class="admin-stats__tile">
                <b><?= (int) $stats['economy']['gemsInCirculation'] ?></b>
                <span>gems in circulation</span>
            </li>
            <li class="admin-stats__tile">
                <b><?= (int) $stats['economy']['itemsHeld'] ?></b>
                <span>items in pockets</span>
            </li>
        </ul>
        <p class="field__hint">
            What the shop SOLD this week isn&rsquo;t knowable yet — there is
            no order ledger, only what people hold now. If that number ever
            matters for balancing, a ledger is the honest way to get it.
        </p>
    </div>

    <div class="card">
        <h3>Where people wander</h3>
        <p class="field__hint">
            Counted from visitors&rsquo; current search windows begun this
            week — a faithful sketch, not a ledger (the visit table keeps
            windows, not history).
        </p>
        <ul class="admin-stats__bars">
            <?php foreach ($stats['areas'] as $area): ?>
                <li>
                    <span class="admin-stats__bar-name"><?= $this->e($area['name']) ?></span>
                    <b><?= (int) $area['searches'] ?></b>
                    <span class="admin-stats__quiet">searches by <?= (int) $area['wanderers'] ?> wanderer<?= $area['wanderers'] === 1 ? '' : 's' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
