<?php
/**
 * Your friends list (M6.1) — bookmarks to people whose creatures you like
 * visiting — and the people you've blocked.
 *
 * @package Felkyo\Templates
 *
 * Rendered by CompanyController::show.
 * Variables: $friends, $blocked (arrays of ['id' => int, 'username' => string]).
 */
$this->layout('layout', ['title' => 'Friends — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->t('friends.friends', 'Friends') ?></h2>
<p>
    <?= $this->t('friends.a-bookmark-for-people-whose', 'A bookmark for people whose creatures you like visiting. Adding someone tells them nothing and unlocks nothing — it just saves you looking them up. Add people from their own page.') ?>
</p>

<?php if ($friends === []): ?>
    <p class="empty-state"><?= $this->t('friends.nobody-bookmarked-yet-visit-someone', 'Nobody bookmarked yet. Visit someone from the') ?> <a href="/community"><?= $this->t('friends.community', 'community') ?></a> <?= $this->t('friends.page-and-add-them', 'page and add them.') ?></p>
<?php else: ?>
    <ul class="company-list">
        <?php foreach ($friends as $friend): ?>
            <li class="company-list__row card">
                <a class="company-list__name" href="/player/<?= $this->e(rawurlencode($friend['username'])) ?>"><?= $this->e($friend['username']) ?></a>
                <a class="btn btn--secondary" href="/post-office?to=<?= $this->e(rawurlencode($friend['username'])) ?>"><?= $this->t('friends.send-a-card', 'Send a card') ?></a>
                <form method="post" action="/player/<?= $this->e(rawurlencode($friend['username'])) ?>/unfriend">
                    <?= $this->csrf_field() ?>
                    <input type="hidden" name="from" value="friends">
                    <button class="btn--quiet" type="submit"><?= $this->t('friends.remove', 'remove') ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p class="company-more">
    <?= $this->t('friends.also', 'Also:') ?> <a href="/post-office"><?= $this->t('friends.the-post-office', 'the post office') ?></a> &middot; <a href="/shore"><?= $this->t('friends.the-shore', 'the shore') ?></a><?= $this->t('friends.where-messages-in-bottles-wash', ', where messages in bottles wash up.') ?>
</p>

<?php if ($blocked !== []): ?>
    <h3 class="library-section"><?= $this->t('friends.blocked', 'Blocked') ?></h3>
    <p class="library-hint"><?= $this->t('friends.cards-and-bottles-from-these', 'Cards and bottles from these people don\'t reach you. They aren\'t told.') ?></p>
    <ul class="company-list">
        <?php foreach ($blocked as $person): ?>
            <li class="company-list__row card">
                <span class="company-list__name"><?= $this->e($person['username']) ?></span>
                <form method="post" action="/player/<?= $this->e(rawurlencode($person['username'])) ?>/unblock">
                    <?= $this->csrf_field() ?>
                    <input type="hidden" name="from" value="friends">
                    <button class="btn--quiet" type="submit"><?= $this->t('friends.unblock', 'unblock') ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
