<?php
/**
 * A single creature's page.
 *
 * @package Felkyo\Templates
 *
 * Shows one creature: the sheet (portrait, name, mood, stats, doings — a
 * partial since M2.3, shared with the species editor's preview so both are
 * always the same markup), then the bio, then the guestbook. Rendered by
 * CreatureController.
 *
 * Variables:
 *   $creature (Felkyo\Creatures\Creature)
 *   $species  (Felkyo\Creatures\Species|null)
 *   $owner    (Felkyo\Users\User|null)
 *   $stage    (string) — "baby" | "juvenile" | "adult", worked out from XP
 *   $level    (int)    — worked out from XP
 *   $timesPetted (int)
 *   $mood     (Felkyo\Creatures\Mood)
 *   $canPet   (bool)   — whether the current visitor may pet it
 *   $isOwner  (bool)
 *   $treats   (array)  — the owner's treats, for the chooser
 *   $flash    (string|null) — a one-time message (e.g. the result of petting)
 *   $guestbook (array) — the guestbook panel data (see partials/guestbook.php)
 *   $canSignGuestbook (bool)
 *   $nameMaxLength (int)
 *   $viewerIsLoggedIn (bool)
 *   $sectionOrders (array) — the panel's chosen order of the parts (Site look)
 *
 * THE PARTS CAN BE REORDERED in the panel (Site look). Each part is captured
 * as a named Plates section below, then printed in the chosen order. A part
 * that prints nothing (the share box without a site address, the keeping
 * panel for a visitor) is simply skipped, rule and all.
 */
$this->layout('layout', ['title' => $this->e($creature->name) . ' — Felkyo Creatures']);
?>

<?php $this->start('part-sheet') ?>
<?= $this->insert('partials/creature-sheet', [
    'creature' => $creature,
    'species' => $species,
    'stage' => $stage,
    'level' => $level,
    'timesPetted' => $timesPetted,
    'mood' => $mood,
    'owner' => $owner,
    'canPet' => $canPet,
    'isOwner' => !empty($isOwner),
    'treats' => $treats ?? [],
]) ?>
<?php $this->stop() ?>

<?php $this->start('part-share') ?>
<?php if (!empty($siteUrl) && $species !== null): ?>
    <?= $this->insert('partials/creature-share', [
        'creature' => $creature, 'imagePath' => $species->spriteUrlFor($stage), 'siteUrl' => $siteUrl,
    ]) ?>
<?php endif; ?>
<?php $this->stop() ?>

<?php $this->start('part-character') ?>
<?= $this->insert('partials/creature-character', [
    'creature' => $creature, 'stage' => $stage, 'character' => $character ?? null,
]) ?>
<?php $this->stop() ?>

<?php $this->start('part-about') ?>
<h2 class="panel-label"><?= $this->t('creature.about', 'About {name}', ['name' => $creature->name]) ?></h2>
<div class="card">
    <?php if ($creature->isBioHidden()): ?>
        <?php /* Reported and hidden until a human has looked (M1.4). A neutral
                 placeholder rather than an empty space, worded so it accuses
                 nobody — most reports turn out to be about nothing at all. */ ?>
        <p class="under-review"><?= $this->t('creature.this-description-is-hidden-while', 'This description is hidden while we take a look at it.') ?></p>
    <?php elseif (!empty($creature->bio)): ?>
        <p><?= $this->e($creature->bio) ?></p>
    <?php else: ?>
        <p class="empty-state"><?= $this->t('creature.doesn-t-have-a-bio', '{name} doesn’t have a bio yet.', ['name' => $creature->name]) ?></p>
    <?php endif; ?>

    <?php if (empty($isOwner) && !empty($viewerIsLoggedIn)): ?>
        <?= $this->insert('partials/report-link', ['subject' => 'creature_bio', 'id' => $creature->id]) ?>
    <?php endif; ?>

    <?php if (!empty($isOwner)): ?>
        <?php /* The name and the bio: the words this creature's owner chooses and
                 everybody else reads. Their own partial because they are one
                 thing, and because it keeps this page inside its size limit. */ ?>
        <?= $this->insert('partials/creature-words', [
            'creature' => $creature,
            'nameMaxLength' => $nameMaxLength,
        ]) ?>
    <?php endif; ?>
</div>
<?php $this->stop() ?>

<?php $this->start('part-guestbook') ?>
<?= $this->insert('partials/guestbook', [
    'creature' => $creature,
    'guestbook' => $guestbook,
    'canSign' => $canSignGuestbook,
    'isOwner' => !empty($isOwner),
]) ?>
<?php $this->stop() ?>

<?php $this->start('part-keeping') ?>
<?php if (!empty($isOwner)): ?>
    <?= $this->insert('partials/creature-keeping', ['creature' => $creature, 'stage' => $stage]) ?>
<?php endif; ?>
<?php $this->stop() ?>

<?php
// Print the parts in the chosen order (SiteLook fills in any part a saved
// order lacks). A thin rule separates the bigger parts, as it always has.
$printedAny = false;
foreach ($sectionOrders['creature'] ?? array_keys(\Felkyo\Look\SiteLook::SECTIONS['creature']) as $part) {
    $html = trim($this->section('part-' . $part) ?? '');
    if ($html === '') {
        continue;
    }
    if ($printedAny && in_array($part, ['about', 'guestbook', 'keeping'], true)) {
        echo '<hr class="rule">', "\n";
    }
    // Already-escaped HTML from the parts above, so it is printed as it is.
    echo $html, "\n";
    $printedAny = true;
}
