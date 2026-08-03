<?php
/**
 * A single creature's page.
 *
 * @package Felkyo\Templates
 *
 * Shows one creature: its animated portrait, name, species, owner, when it was
 * created, and its current state (stage, XP, happiness). Rendered by
 * CreatureController.
 *
 * Variables:
 *   $creature (Felkyo\Creatures\Creature)
 *   $species  (Felkyo\Creatures\Species|null)
 *   $owner    (Felkyo\Users\User|null)
 *   $stage    (string) — "baby" | "juvenile" | "adult", worked out from XP
 */
$this->layout('layout', ['title' => $this->e($creature->name) . ' — Felkyo Creatures']);

// The species' name and slug, guarded in case the species could not be loaded.
$speciesName = $species?->name ?? 'Unknown species';
$speciesSlug = $species?->slug ?? '';

// The animated image lives at a path built from the species slug and the current
// stage (see the Species class). e.g. /assets/creatures/foxlen/baby.gif
$imagePath = '/assets/creatures/' . rawurlencode($speciesSlug) . '/' . rawurlencode($stage) . '.gif';

// A friendly "created" date, if we have one.
$createdLabel = $creature->createdAt !== null
    ? date('j M Y', (int) strtotime($creature->createdAt))
    : 'unknown';
?>

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
            &middot; <?= $this->e($stage) ?>
            &middot; cared for by <?= $this->e($owner?->username ?? 'someone') ?>
            &middot; here since <?= $this->e($createdLabel) ?>
        </p>

        <!-- Current state. These grow once interaction and levelling arrive
             (increments B.1, B.2); for now a new creature shows all zeroes. -->
        <div class="card card--dark creature-stats">
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e($stage) ?></span>
                <span class="creature-stat__label">stage</span>
            </div>
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e((string) $creature->xp) ?></span>
                <span class="creature-stat__label">xp</span>
            </div>
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e((string) $creature->happiness) ?></span>
                <span class="creature-stat__label">happiness</span>
            </div>
        </div>
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
