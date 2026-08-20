<?php
/**
 * The panel's front page — tiles for the sections your roles open.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminHomeController for staff only. Sections whose screens
 * arrive in a later increment say so honestly, with the increment named —
 * signposts, never dead ends (golden rule 3).
 *
 * Variables: $heldRoles (Role[]), $sections (arrays with title, sentence,
 * href, arrives — see AdminHomeController::sectionsFor()).
 */
$this->layout('layout', ['title' => 'The panel — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>The panel</h2>
        <p class="admin-panel__roles">
            You&rsquo;re here as:
            <?php foreach ($heldRoles as $role): ?>
                <span class="admin-panel__role-chip"><?= $this->e($role->label()) ?></span>
            <?php endforeach; ?>
        </p>
    </header>

    <div class="admin-panel__tiles">
        <?php foreach ($sections as $section): ?>
            <?php if ($section['href'] !== null): ?>
                <?php /* A real section: the whole tile is the link, so the
                         touch target is the card, not a word inside it. */ ?>
                <a class="admin-tile card" href="<?= $this->e($section['href']) ?>">
                    <h3 class="admin-tile__title"><?= $this->e($section['title']) ?></h3>
                    <p class="admin-tile__sentence"><?= $this->e($section['sentence']) ?></p>
                </a>
            <?php else: ?>
                <?php /* A section that exists in the plan but not yet in the
                         code. Not a link (a link that scolds is a dead end) —
                         a quiet card saying when it arrives. */ ?>
                <div class="admin-tile admin-tile--later card">
                    <h3 class="admin-tile__title"><?= $this->e($section['title']) ?></h3>
                    <p class="admin-tile__sentence"><?= $this->e($section['sentence']) ?></p>
                    <p class="admin-tile__arrives">Arrives with <?= $this->e($section['arrives']) ?></p>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
