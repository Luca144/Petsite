<?php
/**
 * The pound (M3.1) — forbidding outside, warm and cosy inside.
 *
 * @package Felkyo\Templates
 *
 * Rendered by PoundController::show. Public; adopting needs an account.
 *
 * THE CONTRAST IS THE POINT (build plan M3.1): the page opens on a cold
 * iron gate in grey stone, and past it everything is parchment, lamplight
 * and creatures waiting for someone. The gate is decoration only
 * (aria-hidden); the heading carries the meaning in words.
 *
 * Variables: $waiting (summaries ready for a home), $settlingIn (int),
 * $yours (summaries this player can still reclaim), $graceDays (int),
 * $graceEndsAt (callable: entered-at string → timestamp), $isLoggedIn (bool),
 * $viewerId (?int — to tell the person who brought a creature in).
 */
$this->layout('layout', ['title' => 'The pound — Felkyo Creatures']);
?>

<div class="pound-gate" aria-hidden="true">
    <span class="pound-gate__sign">FELKYO POUND</span>
    <span class="pound-gate__bars"></span>
</div>

<h2 class="panel-label">The pound</h2>
<p class="pound-intro">
    It looks grim from the road, but inside it's warm, and somebody always leaves the lamp on.
    Every creature here is waiting for a home.
</p>

<?php if ($yours !== []): ?>
    <section class="card pound-yours" aria-labelledby="pound-yours">
        <h3 id="pound-yours">Still yours to take home</h3>
        <p>You brought these here. Until the grace period ends, you can change your mind.</p>
        <ul class="pound-grid">
            <?php foreach ($yours as $summary): ?>
                <li class="pound-grid__item">
                    <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
                    <p class="pound-grid__note">until <?= $this->e(date('j M, H:i', $graceEndsAt((string) $summary['creature']->poundEnteredAt))) ?></p>
                    <form method="post" action="/pound/<?= $this->e((string) $summary['creature']->id) ?>/reclaim">
                        <?= $this->csrf_field() ?>
                        <button class="btn btn--primary" type="submit">Take <?= $this->e($summary['creature']->name) ?> home</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section aria-labelledby="pound-waiting">
    <h3 id="pound-waiting" class="library-section">Waiting for a home</h3>
    <?php if ($settlingIn > 0): ?>
        <p class="library-hint">
            <?= $this->e((string) $settlingIn) ?> more <?= $settlingIn === 1 ? 'is' : 'are' ?> still settling in —
            new arrivals rest for <?= $this->e((string) $graceDays) ?> days before they can be adopted.
        </p>
    <?php endif; ?>

    <?php if ($waiting === []): ?>
        <p class="empty-state">Nobody is waiting right now. That's the best kind of empty.</p>
    <?php else: ?>
        <p>Adopting is free. A creature often leaves the pound with a trait or two from its time here &mdash; and sometimes it's simply grateful.</p>
        <ul class="pound-grid">
            <?php foreach ($waiting as $summary): ?>
                <li class="pound-grid__item">
                    <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
                    <?php if ($isLoggedIn && $summary['creature']->poundPreviousOwnerId === $viewerId): ?>
                        <p class="pound-grid__note">You brought this one in &mdash; someone else gets to adopt it.</p>
                    <?php elseif ($isLoggedIn): ?>
                        <form method="post" action="/pound/<?= $this->e((string) $summary['creature']->id) ?>/adopt">
                            <?= $this->csrf_field() ?>
                            <button class="btn btn--primary" type="submit">Give <?= $this->e($summary['creature']->name) ?> a home</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$isLoggedIn): ?>
            <p><a href="/login">Log in</a> to give one of them a home.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>
