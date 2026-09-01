<?php
/**
 * The report queue — the moderator's inbox (M2.7).
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminReportsController (Moderator role). Most urgent
 * first; every row shows the reported words themselves (staff see
 * hidden text — judging it is the job), the voices count, the
 * reporter's track record, and the actions. The action forms post NO
 * target ids — the server derives everything from the report.
 *
 * Variables: $queue (arrays from ModerationService::queue),
 * $timeoutDays (int[]).
 */
$this->layout('layout', ['title' => 'Reports — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Reports</h2>
        <p>
            Most urgent first, oldest first within that. The words shown are
            what was reported, exactly — including text currently hidden
            from players.
        </p>
    </header>

    <?php if (empty($queue)): ?>
        <div class="card">
            <p>The queue is empty. Nothing needs judging — enjoy the quiet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($queue as $report): ?>
            <article class="card admin-report">
                <header class="admin-report__head">
                    <span class="admin-report__priority admin-report__priority--<?= (int) $report['priority'] ?>">
                        priority <?= (int) $report['priority'] ?>
                    </span>
                    <span class="admin-report__reason"><?= $this->e($report['reason']) ?></span>
                    <span class="admin-audit__subject"><?= $this->e($report['created_at']) ?></span>
                </header>

                <p class="admin-report__words">&ldquo;<?= $this->e($report['subject_words']) ?>&rdquo;</p>

                <p class="admin-report__who">
                    About
                    <a href="/admin/accounts/<?= $this->e((string) $report['about_user_id']) ?>">
                        <?= $this->e($report['about_name']) ?></a>
                    (their <?= $this->e(str_replace('_', ' ', (string) $report['subject_type'])) ?>)
                    &middot; reported by <?= $this->e($report['reporter_name']) ?>
                    &middot; <?= (int) $report['voices'] ?> voice<?= (int) $report['voices'] === 1 ? '' : 's' ?> on this
                </p>
                <?php if ((int) $report['reporter_closed'] > 0): ?>
                    <p class="admin-report__record">
                        <?php // The spite-report signal: how this reporter's past reports fared. ?>
                        This reporter&rsquo;s earlier reports:
                        <?= (int) $report['reporter_dismissed'] ?> of <?= (int) $report['reporter_closed'] ?>
                        closed as dismissed.
                    </p>
                <?php endif; ?>

                <div class="admin-report__actions">
                    <form method="post" action="/admin/reports/<?= $this->e((string) $report['id']) ?>/dismiss">
                        <?= $this->csrf_field() ?>
                        <button class="btn btn--secondary" type="submit">Dismiss (nothing wrong)</button>
                    </form>
                    <?php if (in_array($report['subject_type'], ['creature_bio', 'profile_about'], true)): ?>
                        <form method="post" action="/admin/reports/<?= $this->e((string) $report['id']) ?>/remove-text">
                            <?= $this->csrf_field() ?>
                            <button class="btn btn--secondary" type="submit">Remove the text</button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="/admin/reports/<?= $this->e((string) $report['id']) ?>/rename">
                            <?= $this->csrf_field() ?>
                            <button class="btn btn--secondary" type="submit">Give a neutral name</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/admin/reports/<?= $this->e((string) $report['id']) ?>/warn">
                        <?= $this->csrf_field() ?>
                        <button class="btn btn--secondary" type="submit">Send a warning</button>
                    </form>
                </div>

                <?php /* The timed states, each with the fixed duration offer as
                         radio chips — no dropdowns, no typed numbers. */ ?>
                <div class="admin-report__timed">
                    <form method="post" action="/admin/reports/<?= $this->e((string) $report['id']) ?>/mute" class="admin-report__timed-form">
                        <?= $this->csrf_field() ?>
                        <span class="field__label">Mute for</span>
                        <?php foreach ($timeoutDays as $index => $days): ?>
                            <label class="admin-report__chip">
                                <input type="radio" name="days" value="<?= (int) $days ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                <span><?= (int) $days ?>d</span>
                            </label>
                        <?php endforeach; ?>
                        <button class="btn btn--secondary" type="submit">Mute</button>
                    </form>
                    <form method="post" action="/admin/reports/<?= $this->e((string) $report['id']) ?>/suspend" class="admin-report__timed-form">
                        <?= $this->csrf_field() ?>
                        <span class="field__label">Suspend for</span>
                        <?php foreach ($timeoutDays as $index => $days): ?>
                            <label class="admin-report__chip">
                                <input type="radio" name="days" value="<?= (int) $days ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                <span><?= (int) $days ?>d</span>
                            </label>
                        <?php endforeach; ?>
                        <button class="btn btn--secondary" type="submit">Suspend</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
