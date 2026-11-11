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
    <span class="pound-gate__sign"><?= $this->t('pound.felkyo-pound', 'FELKYO POUND') ?></span>
    <span class="pound-gate__bars"></span>
</div>

<h2 class="panel-label"><?= $this->t('pound.the-pound', 'The pound') ?></h2>
<p class="pound-intro">
    <?= $this->t('pound.it-looks-grim-from-the', 'It looks grim from the road, but inside it\'s warm, and somebody always leaves the lamp on. Every creature here is waiting for a home.') ?>
</p>

<?php if ($yours !== []): ?>
    <section class="card pound-yours" aria-labelledby="pound-yours">
        <h3 id="pound-yours"><?= $this->t('pound.still-yours-to-take-home', 'Still yours to take home') ?></h3>
        <p><?= $this->t('pound.you-brought-these-here-until', 'You brought these here. Until the grace period ends, you can change your mind.') ?></p>
        <ul class="pound-grid">
            <?php foreach ($yours as $summary): ?>
                <li class="pound-grid__item">
                    <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
                    <p class="pound-grid__note"><?= $this->t('pound.until', 'until {pound_entered_at}', ['pound_entered_at' => date('j M, H:i', $graceEndsAt((string) $summary['creature']->poundEnteredAt))]) ?></p>
                    <form method="post" action="/pound/<?= $this->e((string) $summary['creature']->id) ?>/reclaim">
                        <?= $this->csrf_field() ?>
                        <button class="btn btn--primary" type="submit"><?= $this->t('pound.take-home', 'Take {summary_name} home', ['summary_name' => $summary['creature']->name]) ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section aria-labelledby="pound-waiting">
    <h3 id="pound-waiting" class="library-section"><?= $this->t('pound.waiting-for-a-home', 'Waiting for a home') ?></h3>
    <?php if ($settlingIn > 0): ?>
        <p class="library-hint">
            <?= $this->t('pound.more', '{settling_in} more', ['settling_in' => (string) $settlingIn]) ?> <?= $settlingIn === 1 ? 'is' : 'are' ?> <?= $this->t('pound.still-settling-in-new-arrivals', 'still settling in — new arrivals rest for {grace_days} days before they can be adopted.', ['grace_days' => (string) $graceDays]) ?>
        </p>
    <?php endif; ?>

    <?php if ($waiting === []): ?>
        <p class="empty-state"><?= $this->t('pound.nobody-is-waiting-right-now', 'Nobody is waiting right now. That\'s the best kind of empty.') ?></p>
    <?php else: ?>
        <p><?= $this->t('pound.adopting-is-free-a-creature', 'Adopting is free. A creature often leaves the pound with a trait or two from its time here — and sometimes it\'s simply grateful.') ?></p>
        <ul class="pound-grid">
            <?php foreach ($waiting as $summary): ?>
                <li class="pound-grid__item">
                    <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
                    <?php if ($isLoggedIn && $summary['creature']->poundPreviousOwnerId === $viewerId): ?>
                        <p class="pound-grid__note"><?= $this->t('pound.you-brought-this-one-in', 'You brought this one in — someone else gets to adopt it.') ?></p>
                    <?php elseif ($isLoggedIn): ?>
                        <form method="post" action="/pound/<?= $this->e((string) $summary['creature']->id) ?>/adopt">
                            <?= $this->csrf_field() ?>
                            <button class="btn btn--primary" type="submit"><?= $this->t('pound.give-a-home', 'Give {summary_name} a home', ['summary_name' => $summary['creature']->name]) ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$isLoggedIn): ?>
            <p><a href="/login"><?= $this->t('pound.log-in', 'Log in') ?></a> <?= $this->t('pound.to-give-one-of-them', 'to give one of them a home.') ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>
