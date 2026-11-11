<?php
/**
 * The panel's bug list: what players told us is broken.
 *
 * @package Felkyo\Templates
 *
 * Rendered by BugReportController::index (coder role; owner passes). Three
 * tabs — open, fixed, closed — newest first in each. Every player-written
 * value is escaped; the page address was checked to be a path on this site
 * before it was stored, so it is safe to show as a link.
 *
 * Variables: $status (the open tab), $counts (status => int),
 * $reports (arrays from BugReportRepository::withStatus).
 */
$this->layout('layout', ['title' => 'Bug reports — Felkyo Creatures']);

$tabNames = ['open' => 'Open', 'fixed' => 'Fixed', 'closed' => 'Closed'];
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Bug reports</h2>
        <p>What players told us is broken, from the page where it happened. Mark one
           <b>fixed</b> once it&rsquo;s sorted, or <b>closed</b> if it wasn&rsquo;t a bug.</p>
    </header>

    <nav class="community__tabs" aria-label="Bug report status">
        <?php foreach ($tabNames as $value => $name): ?>
            <a class="community__tab" href="/admin/bugs?status=<?= $this->e($value) ?>"<?= $status === $value ? ' aria-current="page"' : '' ?>>
                <?= $this->e($name) ?> (<?= $this->e((string) ($counts[$value] ?? 0)) ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($reports === []): ?>
        <div class="card"><p>Nothing here. <?= $status === 'open' ? 'No open bugs — lovely.' : '' ?></p></div>
    <?php endif; ?>

    <?php foreach ($reports as $report): ?>
        <article class="card bug-item">
            <header class="bug-item__head">
                <span class="bug-item__id">#<?= $this->e((string) $report['id']) ?></span>
                <a href="<?= $this->e($report['page_path']) ?>"><code><?= $this->e($report['page_path']) ?></code></a>
                <span class="bug-item__meta">
                    from <a href="/player/<?= $this->e(rawurlencode($report['reporter'])) ?>"><?= $this->e($report['reporter']) ?></a>
                    &middot; <?= $this->e($report['created_at']) ?>
                </span>
            </header>
            <p class="bug-item__words"><?= nl2br($this->e($report['description'])) ?></p>
            <p class="bug-item__device">
                <?= $report['screen_width'] !== null ? $this->e($report['screen_width'] . 'px wide') . ' &middot; ' : '' ?>
                <?= $this->e($report['browser'] !== '' ? $report['browser'] : 'browser unknown') ?>
            </p>
            <div class="button-row">
                <?php foreach (['fixed' => 'Mark fixed', 'closed' => 'Close — not a bug', 'open' => 'Open again'] as $value => $label): ?>
                    <?php if ($value !== $report['status']): ?>
                        <form method="post" action="/admin/bugs/<?= $this->e((string) $report['id']) ?>/status">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="status" value="<?= $this->e($value) ?>">
                            <input type="hidden" name="back" value="<?= $this->e($status) ?>">
                            <button class="btn btn--secondary" type="submit"><?= $this->e($label) ?></button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>
