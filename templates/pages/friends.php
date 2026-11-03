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

<h2 class="panel-label">Friends</h2>
<p>
    A bookmark for people whose creatures you like visiting. Adding someone tells them nothing and unlocks nothing &mdash;
    it just saves you looking them up. Add people from their own page.
</p>

<?php if ($friends === []): ?>
    <p class="empty-state">Nobody bookmarked yet. Visit someone from the <a href="/community">community</a> page and add them.</p>
<?php else: ?>
    <ul class="company-list">
        <?php foreach ($friends as $friend): ?>
            <li class="company-list__row card">
                <a class="company-list__name" href="/player/<?= $this->e(rawurlencode($friend['username'])) ?>"><?= $this->e($friend['username']) ?></a>
                <a class="btn btn--secondary" href="/post-office?to=<?= $this->e(rawurlencode($friend['username'])) ?>">Send a card</a>
                <form method="post" action="/player/<?= $this->e(rawurlencode($friend['username'])) ?>/unfriend">
                    <?= $this->csrf_field() ?>
                    <input type="hidden" name="from" value="friends">
                    <button class="btn--quiet" type="submit">remove</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<p class="company-more">
    Also: <a href="/post-office">the post office</a> &middot; <a href="/shore">the shore</a>, where messages in bottles wash up.
</p>

<?php if ($blocked !== []): ?>
    <h3 class="library-section">Blocked</h3>
    <p class="library-hint">Cards and bottles from these people don't reach you. They aren't told.</p>
    <ul class="company-list">
        <?php foreach ($blocked as $person): ?>
            <li class="company-list__row card">
                <span class="company-list__name"><?= $this->e($person['username']) ?></span>
                <form method="post" action="/player/<?= $this->e(rawurlencode($person['username'])) ?>/unblock">
                    <?= $this->csrf_field() ?>
                    <input type="hidden" name="from" value="friends">
                    <button class="btn--quiet" type="submit">unblock</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
