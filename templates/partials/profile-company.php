<?php
/**
 * On a player's page (M6): the visitor's buttons — add to friends, send a
 * card, block — and the mantelpiece of cards this player has received.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/profile.php. The mantelpiece is public on purpose: one
 * player showering another with cards becomes visible rather than hidden.
 *
 * Variables: $profile (Profile), $company (array from CompanyPanel, or null),
 * $isOwner (bool).
 */
if (empty($company)) {
    return;
}
$name = rawurlencode($profile->username);
?>
<?php if ($company['viewerIsLoggedIn'] && !$isOwner): ?>
    <div class="profile-company">
        <form method="post" action="/player/<?= $this->e($name) ?>/<?= $company['isFriend'] ? 'unfriend' : 'friend' ?>">
            <?= $this->csrf_field() ?>
            <button class="btn btn--secondary" type="submit">
                <?= $company['isFriend'] ? '&#9733; on your friends list' : '&#9734; add to friends' ?>
            </button>
        </form>
        <a class="btn btn--secondary" href="/post-office?to=<?= $this->e($name) ?>"><?= $this->t('profile-company.send-a-card', 'Send a card') ?></a>
        <form method="post" action="/player/<?= $this->e($name) ?>/<?= $company['isBlocked'] ? 'unblock' : 'block' ?>">
            <?= $this->csrf_field() ?>
            <button class="btn--quiet" type="submit"><?= $company['isBlocked'] ? 'unblock' : 'block' ?></button>
        </form>
    </div>
<?php endif; ?>

<?= $this->insert('partials/badge-shelf', ['badges' => $company['badges'] ?? [], 'heading' => 'Badges']) ?>

<section class="mantelpiece" aria-labelledby="mantelpiece">
    <h2 id="mantelpiece" class="panel-label"><?= $this->t('profile-company.on-the-mantelpiece', 'On the mantelpiece') ?></h2>
    <?php if ($company['cards'] === []): ?>
        <p class="empty-state"><?= $this->t('profile-company.no-cards-yet', 'No cards yet.') ?></p>
    <?php else: ?>
        <ul class="mantelpiece__cards">
            <?php foreach ($company['cards'] as $card): ?>
                <li class="mantelpiece__card">
                    <?= $this->insert('partials/postcard', ['card' => $card]) ?>
                    <span class="mantelpiece__from"><?= $this->t('profile-company.from', 'from') ?> <a href="/player/<?= $this->e(rawurlencode($card['sender'])) ?>"><?= $this->e($card['sender']) ?></a></span>
                    <?php if ($isOwner): ?>
                        <?= $this->insert('partials/report-link', ['subject' => 'postcard', 'id' => $card['id']]) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
