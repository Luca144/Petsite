<?php
/**
 * The audit page — everything staff have done in the panel, newest first.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminAuditController, owner-only, read-only. Each entry is one
 * stacked block rather than a wide table row: the log must be readable on a
 * phone, and a nine-column table at 360px is not reading, it is archaeology.
 *
 * Variables: $entries (arrays with who, what, subjectType, subjectId,
 * before, after, ip, when — see the controller).
 */
$this->layout('layout', ['title' => 'Audit log — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Audit log</h2>
        <p>
            Everything staff have done here, newest first. Nothing on this
            page can be edited or removed &mdash; that is the point of it.
        </p>
    </header>

    <?php if (empty($entries)): ?>
        <div class="card">
            <p>Nothing has happened yet. The first entry will be the founding of the first owner.</p>
        </div>
    <?php else: ?>
        <ol class="admin-audit__entries">
            <?php foreach ($entries as $entry): ?>
                <li class="admin-audit__entry card">
                    <p class="admin-audit__line">
                        <strong><?= $this->e($entry['who']) ?></strong>
                        <?= $this->e($entry['what']) ?><?php
                        if ($entry['subjectType'] !== null): ?>
                            <span class="admin-audit__subject">
                                (<?= $this->e($entry['subjectType']) ?> #<?= $this->e((string) $entry['subjectId']) ?>)
                            </span>
                        <?php endif; ?>
                    </p>

                    <?php if ($entry['before'] !== null || $entry['after'] !== null): ?>
                        <p class="admin-audit__change">
                            <?php if ($entry['before'] !== null): ?>
                                <span class="admin-audit__before">before: <?= $this->e($entry['before']) ?></span>
                            <?php endif; ?>
                            <?php if ($entry['after'] !== null): ?>
                                <span class="admin-audit__after">after: <?= $this->e($entry['after']) ?></span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <p class="admin-audit__meta">
                        <?= $this->e($entry['when']) ?> &middot; from <?= $this->e($entry['ip']) ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
