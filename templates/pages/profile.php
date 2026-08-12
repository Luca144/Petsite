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
 */
$this->layout('layout', ['title' => $profile->username . ' — Felkyo Creatures']);
?>

<?php if (!empty($flash)): ?>
    <p class="flash" role="status"><?= $this->e($flash) ?></p>
<?php endif; ?>

<header class="profile-header">
    <img class="profile-header__avatar pixelated"
         src="<?= $this->e($avatarPath) ?>"
         alt="<?= $this->e($profile->username . '’s avatar: ' . $avatarName) ?>"
         width="96" height="96">

    <div class="profile-header__text">
        <h2 class="profile-header__name"><?= $this->e($profile->username) ?></h2>

        <?php if ($profile->createdAt !== null): ?>
            <p class="profile-header__joined">
                Wandering Felkyo since
                <time datetime="<?= $this->e(substr($profile->createdAt, 0, 10)) ?>">
                    <?= $this->e(date('j F Y', strtotime($profile->createdAt))) ?>
                </time>
            </p>
        <?php endif; ?>
    </div>
</header>

<?php if ($isOwner): ?>
    <p class="profile-owner-note">
        This is how your page looks to everyone else.
        <a href="/profile/edit">Change your page</a>
    </p>
<?php endif; ?>

<section class="profile-about">
    <h3 class="panel-label">About</h3>

    <?php if ($profile->isAboutHidden()): ?>
        <?php /* Reported and hidden until a human has looked. A neutral
                 placeholder rather than nothing at all, so the page does not
                 silently lose a section — and worded so it accuses nobody, since
                 most reports turn out to be about nothing. */ ?>
        <p class="under-review">This text is hidden while we take a look at it.</p>
    <?php elseif ($profile->hasAbout()): ?>
        <?php /* Plates escapes this. nl2br is applied to the ESCAPED text, so the
                 only tags that can reach the page are the <br> we add ourselves —
                 never anything somebody typed. */ ?>
        <p class="profile-about__text"><?= nl2br($this->e($profile->about)) ?></p>
    <?php elseif ($isOwner): ?>
        <p class="empty-state">
            You haven&rsquo;t written anything here yet.
            <a href="/profile/edit">Say hello?</a>
        </p>
    <?php else: ?>
        <p class="empty-state">
            <?= $this->e($profile->username) ?> hasn&rsquo;t written anything here yet.
        </p>
    <?php endif; ?>
    <?php if ($canReport): ?>
        <?= $this->insert('partials/report-link', ['subject' => 'profile_about', 'id' => $profile->id]) ?>
    <?php endif; ?>
</section>

<section class="profile-creatures">
    <h3 class="panel-label">Creatures</h3>

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
        <?php $star = $spotlight['creature']; ?>
        <a class="spotlight<?= $star->isFeatured() ? ' spotlight--favourite' : '' ?>"
           href="/creature/<?= $this->e((string) $star->id) ?>">
            <img class="spotlight__img pixelated"
                 src="/assets/creatures/<?= $this->e(rawurlencode($spotlight['species']?->slug ?? '')) ?>/<?= $this->e(rawurlencode($spotlight['stage'])) ?>.gif"
                 alt="<?= $this->e($star->name) ?>"
                 width="180" height="180">

            <span class="spotlight__text">
                <?php if ($star->isFeatured()): ?>
                    <span class="spotlight__label">
                        <span aria-hidden="true">&#9733;</span> <?= $isOwner ? 'your favourite' : 'their favourite' ?>
                    </span>
                <?php endif; ?>
                <span class="spotlight__name"><?= $this->e($star->name) ?></span>
                <span class="spotlight__meta">
                    <?= $this->e($spotlight['species']?->name ?? 'Unknown') ?>
                    &middot; level <?= $this->e((string) $spotlight['level']) ?>
                    <?php /* Happiness, not times-petted: the count needs its own query
                             per creature, and a profile showing a dozen of them would
                             quietly become a dozen extra queries. Happiness is already
                             on the creature. */ ?>
                    &middot; happiness <?= $this->e((string) $star->happiness) ?>
                </span>
            </span>
        </a>
    <?php endif; ?>

    <?php if (empty($others) && $spotlight !== null): ?>
        <?php /* Nothing to add — the spotlight is the whole collection. */ ?>
    <?php elseif (empty($summaries)): ?>
        <p class="empty-state">
            <?php if ($isOwner): ?>
                None of your creatures are on show here yet.
                <a href="/profile/edit">Choose some</a>, or make one public from its own page.
            <?php else: ?>
                <?= $this->e($profile->username) ?> isn&rsquo;t showing any creatures at the moment.
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
            private, so <?= $hiddenCount === 1 ? 'it does' : 'they do' ?> not appear here.
            You can change that on <?= $hiddenCount === 1 ? 'its' : 'their' ?> own page.
        </p>
    <?php endif; ?>
</section>
