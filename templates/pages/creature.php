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
    <div class="creature__portrait">
        <img class="creature__img pixelated"
             src="<?= $this->e($imagePath) ?>"
             alt="<?= $this->e($creature->name . ', a ' . $speciesName . ' (' . $stage . ')') ?>"
             width="240" height="240">
    </div>

    <div class="creature__info">
        <h1><?= $this->e($creature->name) ?></h1>
        <p class="creature__meta">
            a <b><?= $this->e($speciesName) ?></b>
            &middot; cared for by <?= $this->e($owner?->username ?? 'someone') ?>
            &middot; here since <?= $this->e($createdLabel) ?>
        </p>

        <!-- Current state. Level, stage and happiness rise as the creature is
             petted (increments B.1, B.2). -->
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
                <span class="creature-stat__value"><?= $this->e((string) $creature->happiness) ?></span>
                <span class="creature-stat__label">happiness</span>
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
    <?php if (!empty($creature->bio)): ?>
        <p><?= $this->e($creature->bio) ?></p>
    <?php else: ?>
        <p class="empty-state"><?= $this->e($creature->name) ?> doesn&rsquo;t have a bio yet.</p>
    <?php endif; ?>
</div>
