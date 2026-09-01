<?php
/**
 * Settings & maintenance — the Coder's screen (M2.8).
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminSettingsController. Each setting is its own little
 * form with its explanation beside it; each maintenance job is its own
 * NAMED button. There is deliberately nothing free-form on this page.
 *
 * Variables: $rows (key/entry/current/isOverridden), $artUsedBytes,
 * $artWarningBytes.
 */
$this->layout('layout', ['title' => 'Settings — Felkyo Creatures']);

// Bytes → a friendly "123 MB" (one decimal below 10 MB, whole above).
$asMegabytes = static function (int $bytes): string {
    $megabytes = $bytes / (1024 * 1024);

    return $megabytes < 10 ? number_format($megabytes, 1) . ' MB' : number_format($megabytes, 0) . ' MB';
};
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Settings &amp; maintenance</h2>
        <p>
            The game&rsquo;s tunable numbers — changes take effect with the
            next page anyone loads, and every change lands in the audit log.
            Only what is listed here can be changed; there is no general
            editor, on purpose.
        </p>
    </header>

    <?php foreach ($rows as $row): ?>
        <?php $entry = $row['entry']; ?>
        <div class="card admin-setting">
            <form method="post" action="/admin/settings/<?= $this->e($row['key']) ?>">
                <?= $this->csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="setting-<?= $this->e($row['key']) ?>">
                        <?= $this->e($entry['label']) ?>
                        <?php if ($row['isOverridden']): ?>
                            <span class="admin-setting__source admin-setting__source--changed">changed here</span>
                        <?php else: ?>
                            <span class="admin-setting__source">built-in</span>
                        <?php endif; ?>
                    </label>
                    <p class="field__hint"><?= $this->e($entry['explanation']) ?></p>

                    <?php if ($entry['type'] === 'toggle'): ?>
                        <label class="admin-setting__toggle">
                            <input type="checkbox" name="value" <?= $row['current'] ? 'checked' : '' ?>>
                            <span><?= $row['current'] ? 'On — untick and save to switch off' : 'Off — tick and save to switch on' ?></span>
                        </label>
                    <?php elseif ($entry['type'] === 'number'): ?>
                        <input class="field__input admin-setting__number" type="number"
                               id="setting-<?= $this->e($row['key']) ?>" name="value"
                               min="<?= (int) $entry['min'] ?>" max="<?= (int) $entry['max'] ?>"
                               value="<?= $this->e((string) $row['current']) ?>">
                    <?php else: ?>
                        <textarea class="field__input admin-setting__words"
                                  id="setting-<?= $this->e($row['key']) ?>" name="value"
                                  rows="<?= max(3, count((array) $row['current']) + 1) ?>"
                                  spellcheck="false"><?= $this->e(implode("\n", (array) $row['current'])) ?></textarea>
                    <?php endif; ?>
                </div>
                <button class="btn btn--primary" type="submit">Save</button>
            </form>
            <?php if ($row['isOverridden']): ?>
                <form method="post" action="/admin/settings/<?= $this->e($row['key']) ?>/reset">
                    <?= $this->csrf_field() ?>
                    <button class="btn--quiet" type="submit">Back to the built-in value</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="card">
        <h3>Maintenance</h3>
        <p class="field__hint">
            A small set of named jobs — each one does exactly what it says
            and writes itself into the audit log. If a new job is ever
            needed, it becomes a new named button here; nothing on this
            site runs typed commands, ever.
        </p>

        <div class="admin-setting__job">
            <h4>Back up the art</h4>
            <p>
                Every uploaded picture as one ZIP.
                The store holds <?= $this->e($asMegabytes($artUsedBytes)) ?>
                (the warning light comes on at <?= $this->e($asMegabytes($artWarningBytes)) ?>).
                <strong>The volume has no automatic backups</strong> — this
                button is the backup, so press it after big art days and keep
                the file somewhere safe.
            </p>
            <a class="btn btn--secondary" href="/admin/maintenance/art-backup">Download the backup</a>
        </div>

        <div class="admin-setting__job">
            <h4>Clear the rate-limit slate</h4>
            <p>
                Empties every "slow down" counter — for the day somebody
                locks themselves out testing. It touches nothing but the
                counters; no game data is involved.
            </p>
            <form method="post" action="/admin/maintenance/clear-rate-limits">
                <?= $this->csrf_field() ?>
                <label class="admin-setting__confirm">
                    <input type="checkbox" name="confirm" value="yes">
                    <span>Yes, clear them — I mean it</span>
                </label>
                <button class="btn btn--secondary" type="submit">Clear the slate</button>
            </form>
        </div>
    </div>
</section>
