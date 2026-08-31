<?php
/**
 * The unfinished list — what still needs a hand, gathered by the site.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminUnfinishedController (Artist role). Two tiers from
 * UnfinishedListService: drafts that can't publish yet, and published
 * things wearing a fallback. Every row links to the editor that fixes it;
 * an empty tier says something warm instead of nothing.
 *
 * Variables: $blocked, $niceties (arrays of what/name/href/needs).
 */
$this->layout('layout', ['title' => 'Unfinished — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Unfinished</h2>
        <p>
            The site keeps this list so nobody has to. Everything here links
            straight to the page where it gets finished.
        </p>
    </header>

    <div class="card">
        <h3>Waiting to publish
            <span class="admin-audit__subject"><?= count($blocked) ?></span>
        </h3>
        <?php if (empty($blocked)): ?>
            <p>Nothing is stuck in draft. Everything you have started is out in the world.</p>
        <?php else: ?>
            <ul class="admin-unfinished__list">
                <?php foreach ($blocked as $row): ?>
                    <li class="admin-unfinished__row">
                        <span class="admin-unfinished__what"><?= $this->e($row['what']) ?></span>
                        <a href="<?= $this->e($row['href']) ?>"><?= $this->e($row['name']) ?></a>
                        <span class="admin-unfinished__needs"><?= $this->e($row['needs']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Published, but could be more
            <span class="admin-audit__subject"><?= count($niceties) ?></span>
        </h3>
        <p class="field__hint">
            Nothing here is broken — the site shows a sensible stand-in for
            each. This is the polishing list, for whenever there&rsquo;s a
            quiet hour.
        </p>
        <?php if (empty($niceties)): ?>
            <p>Not a thing. Every published piece has all its trimmings — enjoy the moment.</p>
        <?php else: ?>
            <ul class="admin-unfinished__list">
                <?php foreach ($niceties as $row): ?>
                    <li class="admin-unfinished__row">
                        <span class="admin-unfinished__what"><?= $this->e($row['what']) ?></span>
                        <a href="<?= $this->e($row['href']) ?>"><?= $this->e($row['name']) ?></a>
                        <span class="admin-unfinished__needs"><?= $this->e($row['needs']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
