<?php
/**
 * A single creature's page.
 *
 * @package Felkyo\Templates
 *
 * Shows one creature: its animated portrait, name, species, owner, when it was
 * created, and its current state (level, stage, happiness, times petted). Logged-in
 * visitors get a "Pet" button. Rendered by CreatureController.
 *
 * Variables:
 *   $creature (Felkyo\Creatures\Creature)
 *   $species  (Felkyo\Creatures\Species|null)
 *   $owner    (Felkyo\Users\User|null)
 *   $stage    (string) — "baby" | "juvenile" | "adult", worked out from XP
 *   $level    (int)    — worked out from XP
 *   $timesPetted (int)
 *   $canPet   (bool)   — whether the current visitor may pet it
 *   $flash    (string|null) — a one-time message (e.g. the result of petting)
 *   $guestbook (array) — the guestbook panel data (see partials/guestbook.php)
 *   $canSignGuestbook (bool)
 */
$this->layout('layout', ['title' => $this->e($creature->name) . ' — Felkyo Creatures']);

$speciesName = $species?->name ?? 'Unknown species';
$speciesSlug = $species?->slug ?? '';

// The animated image path is built from the species slug and the current stage
// (see the Species class). e.g. /assets/creatures/foxlen/baby.gif
$imagePath = '/assets/creatures/' . rawurlencode($speciesSlug) . '/' . rawurlencode($stage) . '.gif';

$createdLabel = $creature->createdAt !== null
    ? date('j M Y', (int) strtotime($creature->createdAt))
    : 'unknown';
?>

<?php if (!empty($flash)): ?>
    <!-- A one-time message from the action just taken (role="status" so screen
         readers announce it politely). -->
    <p class="flash" role="status"><?= $this->e($flash) ?></p>
<?php endif; ?>

<article class="creature">
    <?php /* After a pet, --celebrate does three things at once: the creature gives a
             happy wriggle, three hearts drift up at slightly different times, and the
             two numbers underneath light up. One small heart was too quiet to notice —
             petting is the thing players do most on this site and it should feel like
             something happened. All of it is CSS and all of it is skipped entirely
             under prefers-reduced-motion. */ ?>
    <div class="creature__portrait<?= !empty($justPetted) ? ' creature__portrait--celebrate' : '' ?>">
        <img class="creature__img pixelated"
             src="<?= $this->e($imagePath) ?>"
             alt="<?= $this->e($creature->name . ', a ' . $speciesName . ' (' . $stage . ')') ?>"
             width="240" height="240">

        <?php if (!empty($justPetted)): ?>
            <?php /* Purely decorative, so hidden from screen readers — the flash
                     message above already says what happened, in words. */ ?>
            <span class="creature__hearts" aria-hidden="true">
                <span>&hearts;</span><span>&hearts;</span><span>&hearts;</span>
            </span>
        <?php endif; ?>
    </div>

    <div class="creature__info">
        <h1><?= $this->e($creature->name) ?></h1>
        <p class="creature__meta">
            a <b><?= $this->e($speciesName) ?></b>
            <?php /* The owner's name links to their page, so a creature you liked
                     is a way of finding the person who looks after it. */ ?>
            &middot; cared for by
            <?php if ($owner !== null): ?>
                <a href="/player/<?= $this->e(rawurlencode($owner->username)) ?>"><?= $this->e($owner->username) ?></a>
            <?php else: ?>
                someone
            <?php endif; ?>
            &middot; here since <?= $this->e($createdLabel) ?>
        </p>
        <?php if (!empty($species?->flavourText)): ?>
            <!-- A little flavour about the species (increment C.2). -->
            <p class="creature__flavour"><?= $this->e($species->flavourText) ?></p>
        <?php endif; ?>

        <?php /* How the creature is FEELING, in words, above the numbers. This is
                 the line a player should read first — "Biscuit is very happy" is
                 something you can feel about, where "87" is a readout. The bars
                 below carry the same two facts a second way, which is the rule
                 that nothing important may ride on one signal alone. */ ?>
        <p class="creature__mood" role="status">
            <?= $this->e($mood->sentence($creature->name)) ?>
        </p>

        <?= $this->insert('partials/mood-bars', ['mood' => $mood, 'justPetted' => $justPetted ?? false]) ?>

        <!-- Current state. Level and stage rise as the creature is petted. -->
        <div class="card card--dark creature-stats">
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e((string) $level) ?></span>
                <span class="creature-stat__label">level</span>
            </div>
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e($stage) ?></span>
                <span class="creature-stat__label">stage</span>
            </div>
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e((string) $timesPetted) ?></span>
                <span class="creature-stat__label">times petted</span>
            </div>
        </div>

        <?php if ($canPet): ?>
            <form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/pet" class="creature__pet">
                <?= $this->csrf_field() ?>
                <button class="btn btn--primary" type="submit">
                    <span aria-hidden="true">&hearts;</span> Pet <?= $this->e($creature->name) ?>
                </button>
            </form>
        <?php else: ?>
            <p class="auth-alt"><a href="/login">Log in</a> to pet <?= $this->e($creature->name) ?>.</p>
        <?php endif; ?>
    </div>
</article>

<hr class="rule">

<h2 class="panel-label">About <?= $this->e($creature->name) ?></h2>
<div class="card">
    <?php if ($creature->isBioHidden()): ?>
        <?php /* Reported and hidden until a human has looked (M1.4). A neutral
                 placeholder rather than an empty space, worded so it accuses
                 nobody — most reports turn out to be about nothing at all. */ ?>
        <p class="under-review">This description is hidden while we take a look at it.</p>
    <?php elseif (!empty($creature->bio)): ?>
        <p><?= $this->e($creature->bio) ?></p>
    <?php else: ?>
        <p class="empty-state"><?= $this->e($creature->name) ?> doesn&rsquo;t have a bio yet.</p>
    <?php endif; ?>

    <?php if (empty($isOwner) && !empty($viewerIsLoggedIn)): ?>
        <?= $this->insert('partials/report-link', ['subject' => 'creature_bio', 'id' => $creature->id]) ?>
    <?php endif; ?>

    <?php if (!empty($isOwner)): ?>
        <!-- Only the owner sees this. It lets them write or change the bio. -->
        <form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/bio" class="bio-form">
            <?= $this->csrf_field() ?>
            <label class="field__label" for="bio">Edit bio</label>
            <textarea class="field__textarea" id="bio" name="bio" maxlength="500"
                      placeholder="Tell everyone a little about <?= $this->e($creature->name) ?>…"><?= $this->e($creature->bio ?? '') ?></textarea>
            <button class="btn btn--secondary" type="submit">Save bio</button>
        </form>

        <?php /* Renaming, in the same owner-only box as the bio because it is the
                 same kind of act: changing the words attached to your creature.
                 The field is pre-filled with the current name so the common case
                 (a small change) is a small edit, not a retype from nothing. */ ?>
        <form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/rename" class="bio-form">
            <?= $this->csrf_field() ?>
            <label class="field__label" for="creature-name">Rename <?= $this->e($creature->name) ?></label>
            <input class="field__input" type="text" id="creature-name" name="name"
                   value="<?= $this->e($creature->name) ?>"
                   maxlength="<?= $this->e((string) $nameMaxLength) ?>"
                   required
                   aria-describedby="creature-name-hint">
            <span class="field__hint" id="creature-name-hint">
                Up to <?= $this->e((string) $nameMaxLength) ?> characters. You can change this whenever you like.
            </span>
            <button class="btn btn--secondary" type="submit">Save name</button>
        </form>
    <?php endif; ?>
</div>

<hr class="rule">

<?= $this->insert('partials/guestbook', [
    'creature' => $creature,
    'guestbook' => $guestbook,
    'canSign' => $canSignGuestbook,
    'isOwner' => !empty($isOwner),
]) ?>
