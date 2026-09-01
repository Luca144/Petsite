<?php
/**
 * Account history on one page — the pattern view (M2.7).
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminAccountController (Moderator role). Everything about
 * one account together, because judging reports one at a time is how a
 * repeat offender slips through. Staff see hidden text here; judging it
 * is the job.
 *
 * Variables: $profile (Profile), $state (stateFor array), $creatures
 * (Creature[]), $signedGuestbooks, $reportsAbout, $reporterRecord,
 * $noticesSent (ModerationNotice[]), $actionsTaken, $timeoutDays.
 */
$this->layout('layout', ['title' => $this->e($profile->username) . ' — Accounts — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($profile->username) ?></h2>
        <p class="admin-report__who">
            Account #<?= $this->e((string) $profile->id) ?>
            &middot; here since <?= $this->e((string) $profile->createdAt) ?>
            <?php if ($state['is_muted']): ?>
                <span class="admin-account__state">muted until <?= $this->e((string) $state['muted_until']) ?></span>
            <?php endif; ?>
            <?php if ($state['is_suspended']): ?>
                <span class="admin-account__state">suspended until <?= $this->e((string) $state['suspended_until']) ?></span>
            <?php endif; ?>
        </p>
        <?php if ($profile->about !== null): ?>
            <p class="admin-report__words">About text: &ldquo;<?= $this->e($profile->about) ?>&rdquo;
                <?php if ($profile->aboutHiddenAt !== null): ?>
                    <span class="admin-species__missing">hidden pending review</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <div class="admin-report__actions">
            <form method="post" action="/admin/accounts/<?= $this->e((string) $profile->id) ?>/warn">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit">Send a warning</button>
            </form>
            <?php if ($state['is_muted']): ?>
                <form method="post" action="/admin/accounts/<?= $this->e((string) $profile->id) ?>/unmute">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--secondary" type="submit">Lift the mute</button>
                </form>
            <?php endif; ?>
            <?php if ($state['is_suspended']): ?>
                <form method="post" action="/admin/accounts/<?= $this->e((string) $profile->id) ?>/unsuspend">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--secondary" type="submit">Lift the suspension</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="admin-report__timed">
            <form method="post" action="/admin/accounts/<?= $this->e((string) $profile->id) ?>/mute" class="admin-report__timed-form">
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
            <form method="post" action="/admin/accounts/<?= $this->e((string) $profile->id) ?>/suspend" class="admin-report__timed-form">
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
    </header>

    <div class="card">
        <h3>Reports about them <span class="admin-audit__subject"><?= count($reportsAbout) ?></span></h3>
        <?php if (empty($reportsAbout)): ?>
            <p>Never reported. A quiet record.</p>
        <?php else: ?>
            <ul class="admin-account__list">
                <?php foreach ($reportsAbout as $report): ?>
                    <li>
                        <span class="admin-unfinished__what"><?= $this->e((string) $report['status']) ?></span>
                        their <?= $this->e(str_replace('_', ' ', (string) $report['subject_type'])) ?>,
                        reported by <?= $this->e((string) $report['reporter_name']) ?>
                        <span class="admin-audit__subject"><?= $this->e((string) $report['created_at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>As a reporter</h3>
        <p>
            Filed <?= (int) $reporterRecord['filed'] ?>:
            <?= (int) $reporterRecord['actioned'] ?> led to action,
            <?= (int) $reporterRecord['dismissed'] ?> dismissed.
            <?php if ($reporterRecord['dismissed'] > 2 && $reporterRecord['actioned'] === 0): ?>
                <span class="admin-species__missing">pattern: reports that come to nothing</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="card">
        <h3>Their creatures <span class="admin-audit__subject"><?= count($creatures) ?></span></h3>
        <?php if (empty($creatures)): ?>
            <p>No creatures.</p>
        <?php else: ?>
            <ul class="admin-account__list">
                <?php foreach ($creatures as $creature): ?>
                    <li>
                        <a href="/creature/<?= $this->e((string) $creature->id) ?>"><?= $this->e($creature->name) ?></a>
                        <?php if ($creature->bio !== null): ?>
                            &mdash; &ldquo;<?= $this->e($creature->bio) ?>&rdquo;
                            <?php if ($creature->bioHiddenAt !== null): ?>
                                <span class="admin-species__missing">bio hidden pending review</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Guestbooks they signed <span class="admin-audit__subject"><?= count($signedGuestbooks) ?></span></h3>
        <?php if (empty($signedGuestbooks)): ?>
            <p>None signed.</p>
        <?php else: ?>
            <?php /* The PATTERN is what matters here: one name signing the same
                     person's guestbooks over and over is visible as a column. */ ?>
            <ul class="admin-account__list">
                <?php foreach ($signedGuestbooks as $entry): ?>
                    <li>
                        <?= $this->e((string) $entry['creature_name']) ?>
                        (<?= $this->e((string) $entry['owner_name']) ?>&rsquo;s)
                        <span class="admin-audit__subject"><?= $this->e((string) $entry['created_at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Notices they were sent <span class="admin-audit__subject"><?= count($noticesSent) ?></span></h3>
        <?php if (empty($noticesSent)): ?>
            <p>Never needed one.</p>
        <?php else: ?>
            <ul class="admin-account__list">
                <?php foreach ($noticesSent as $notice): ?>
                    <li>
                        <span class="admin-unfinished__what"><?= $this->e($notice->kind) ?></span>
                        <span class="admin-audit__subject"><?= $this->e($notice->createdAt) ?></span>
                        <?= $notice->readAt !== null ? 'read ' . $this->e($notice->readAt) : 'not read yet' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Moderation actions on this account</h3>
        <?php if (empty($actionsTaken)): ?>
            <p>None taken.</p>
        <?php else: ?>
            <ul class="admin-account__list">
                <?php foreach ($actionsTaken as $entry): ?>
                    <li>
                        <?= $this->e((string) ($entry['actor_username'] ?? 'the command line')) ?>
                        <?= $this->e(\Felkyo\Admin\AuditAction::tryFrom((string) $entry['action'])?->label() ?? (string) $entry['action']) ?>
                        <span class="admin-audit__subject"><?= $this->e((string) $entry['created_at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
