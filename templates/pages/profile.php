<?php
/**
 * One player's page — the one other people visit.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ProfileController::show(). Variables: $profile (Profile),
 * $avatarPath, $avatarName, $summaries (creature summaries), $isOwner (bool),
 * $hiddenCount (int, always 0 for visitors), $flash (string|null).
 *
 * THIS PAGE IS THE SAME FOR EVERYBODY. The owner sees exactly what a stranger
 * sees, plus a link to edit it and a quiet note about creatures they have kept
 * private. That is deliberate: the build plan asks that players be shown clearly
 * what others can see, and a page that shows the owner something different would
 * be the one thing guaranteed to mislead them.
 *
 * The about / creatures / company parts can be reordered in the panel (Site
 * look): each is captured as a named section, then printed in that order
 * ($sectionOrders, shared with every template).
 */
$this->layout('layout', ['title' => $profile->username . ' — Felkyo Creatures']);
?>

<header class="profile-header">
    <img class="profile-header__avatar"
         src="<?= $this->e($avatarPath) ?>"
         alt="<?= $this->e($profile->username . "'s avatar: " . $avatarName) ?>"
         width="96" height="96">

    <div class="profile-header__text">
        <h2 class="profile-header__name"><?= $this->e($profile->username) ?></h2>

        <?php if ($profile->createdAt !== null): ?>
            <p class="profile-header__joined">
                <?= $this->t('profile.wandering-felkyo-since', 'Wandering Felkyo since') ?>
                <time datetime="<?= $this->e(substr($profile->createdAt, 0, 10)) ?>">
                    <?= $this->e(date('j F Y', strtotime($profile->createdAt))) ?>
                </time>
            </p>
        <?php endif; ?>
    </div>
</header>

<?php if ($isOwner): ?>
    <p class="profile-owner-note">
        <?= $this->t('profile.this-is-how-your-page', 'This is how your page looks to everyone else.') ?>
        <a href="/profile/edit"><?= $this->t('profile.change-your-page', 'Change your page') ?></a>
        <?= $this->t('profile.or', 'or') ?> <a href="/themes"><?= $this->t('profile.choose-how-felkyo-looks-for', 'choose how Felkyo looks for you') ?></a>.
    </p>
<?php endif; ?>

<?php $this->start('part-about') ?>
<section class="profile-about">
    <h3 class="panel-label"><?= $this->t('profile.about', 'About') ?></h3>

    <?php if ($profile->isAboutHidden()): ?>
        <?php /* Reported and hidden until a human has looked. A neutral
                 placeholder rather than nothing at all, so the page does not
                 silently lose a section — and worded so it accuses nobody, since
                 most reports turn out to be about nothing. */ ?>
        <p class="under-review"><?= $this->t('profile.this-text-is-hidden-while', 'This text is hidden while we take a look at it.') ?></p>
    <?php elseif ($profile->hasAbout()): ?>
        <?php /* Plates escapes this. nl2br is applied to the ESCAPED text, so the
                 only tags that can reach the page are the <br> we add ourselves —
                 never anything somebody typed. */ ?>
        <p class="profile-about__text"><?= nl2br($this->e($profile->about)) ?></p>
    <?php elseif ($isOwner): ?>
        <p class="empty-state">
            <?= $this->t('profile.you-haven-t-written-anything', 'You haven’t written anything here yet.') ?>
            <a href="/profile/edit"><?= $this->t('profile.say-hello', 'Say hello?') ?></a>
        </p>
    <?php else: ?>
        <p class="empty-state">
            <?= $this->t('profile.hasn-t-written-anything-here', '{username} hasn’t written anything here yet.', ['username' => $profile->username]) ?>
        </p>
    <?php endif; ?>
    <?php if ($canReport): ?>
        <?= $this->insert('partials/report-link', ['subject' => 'profile_about', 'id' => $profile->id]) ?>
    <?php endif; ?>
</section>
<?php $this->stop() ?>

<?php $this->start('part-creatures') ?>
<section class="profile-creatures">
    <h3 class="panel-label"><?= $this->t('profile.creatures', 'Creatures') ?></h3>

    <?php
    /*
     * THE SPOTLIGHT. The first creature — the favourite, if one is chosen — is
     * shown large and on its own, above the rest.
     *
     * Choosing a favourite used to earn a small badge on a card the same size as
     * every other card, which is not what "show this one off" means to anybody.
     * A spotlight is: bigger, first, with room around it and its own line of text.
     */
    $spotlight = $summaries[0] ?? null;
    $others = array_slice($summaries, 1);
    ?>

    <?php if ($spotlight !== null): ?>
        <?php
        $star = $spotlight['creature'];
        // One resolver for every sprite address — see Species::spriteUrlFor.
        $spotlightImage = $spotlight['species']?->spriteUrlFor($spotlight['stage']) ?? '';
        // Whole-multiple sizing — a fractional scale smears pixel art (PixelArt).
        [$artWidth, $artHeight] = \Felkyo\Design\PixelArt::displaySize($spotlightImage, 170);
        ?>
        <a class="spotlight<?= $star->isFeatured() ? ' spotlight--favourite' : '' ?>"
           href="/creature/<?= $this->e((string) $star->id) ?>">
            <span class="spotlight__art">
                <img class="spotlight__img pixelated"
                     src="<?= $this->e($spotlightImage) ?>"
                     alt="<?= $this->e($star->name) ?>"
                     width="<?= $this->e((string) $artWidth) ?>" height="<?= $this->e((string) $artHeight) ?>">
            </span>

            <span class="spotlight__text">
                <?php if ($star->isFeatured()): ?>
                    <span class="spotlight__label">
                        <span aria-hidden="true">&#9733;</span> <?= $isOwner ? 'your favourite' : 'their favourite' ?>
                    </span>
                <?php endif; ?>
                <span class="spotlight__name"><?= $this->e($star->name) ?></span>
                <span class="spotlight__meta">
                    <?= $this->t('profile.level', '{spotlight_name} · level {level}', ['spotlight_name' => $spotlight['species']?->name ?? 'Unknown', 'level' => (string) $spotlight['level']]) ?>
                    <?php /* Happiness, not times-petted: the count needs its own query
                             per creature, and a profile showing a dozen of them would
                             quietly become a dozen extra queries. Happiness is already
                             on the creature. */ ?>
                    <?= $this->t('profile.happiness', '· happiness {happiness}', ['happiness' => (string) $star->happiness]) ?>
                </span>
            </span>
        </a>
    <?php endif; ?>

    <?php if (empty($others) && $spotlight !== null): ?>
        <?php /* Nothing to add — the spotlight is the whole collection. */ ?>
    <?php elseif (empty($summaries)): ?>
        <p class="empty-state">
            <?php if ($isOwner): ?>
                <?= $this->t('profile.none-of-your-creatures-are', 'None of your creatures are on show here yet.') ?>
                <a href="/profile/edit"><?= $this->t('profile.choose-some', 'Choose some') ?></a><?= $this->t('profile.or-make-one-public-from', ', or make one public from its own page.') ?>
            <?php else: ?>
                <?= $this->t('profile.isn-t-showing-any-creatures', '{username} isn’t showing any creatures at the moment.', ['username' => $profile->username]) ?>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <div class="creature-grid">
            <?php foreach ($others as $summary): ?>
                <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($isOwner && $hiddenCount > 0): ?>
        <p class="profile-hidden-note">
            <?= $this->e((string) $hiddenCount) ?>
            <?= $hiddenCount === 1 ? 'of your creatures is' : 'of your creatures are' ?>
            <?= $this->t('profile.private-so', 'private, so') ?> <?= $hiddenCount === 1 ? 'it does' : 'they do' ?> <?= $this->t('profile.not-appear-here-you-can', 'not appear here. You can change that on') ?> <?= $hiddenCount === 1 ? 'its' : 'their' ?> <?= $this->t('profile.own-page', 'own page.') ?>
        </p>
    <?php endif; ?>
</section>
<?php $this->stop() ?>

<?php $this->start('part-company') ?>
<?= $this->insert('partials/profile-company', [
    'profile' => $profile, 'company' => $company ?? null, 'isOwner' => $isOwner,
]) ?>
<?php $this->stop() ?>

<?php
// The three middle parts in the order chosen in the panel (Site look);
// the header above and the log-out button below always stay where they are.
foreach ($sectionOrders['profile'] ?? array_keys(\Felkyo\Look\SiteLook::SECTIONS['profile']) as $part) {
    // Already-escaped HTML from the parts above, so it is printed as it is.
    echo $this->section('part-' . $part) ?? '', "
";
}
?>

<?php if ($isOwner): ?>
    <?php /* The way out lives here, at the very bottom of your own page.
             WHY HERE: on a phone the sidebar is a compact strip at the top of
             every single page, so "log out" sat one mis-tap away from the links
             people use all day — and logging out by accident loses your place for
             no reason (golden rule 9: nothing is lost by accident). Your own page
             is somewhere you go deliberately, and the bottom of it is somewhere
             you only reach on purpose. A POST with a CSRF token, never a link, so
             no other site can log you out on your behalf. */ ?>
    <hr class="rule">
    <form method="post" action="/logout" class="profile-logout">
        <?= $this->csrf_field() ?>
        <button class="btn btn--secondary" type="submit"><?= $this->t('profile.log-out', 'Log out') ?></button>
    </form>
<?php endif; ?>
