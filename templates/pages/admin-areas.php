<?php
/**
 * The area list — every place to explore, and the door to a new one.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminAreasController (Artist role). One card per area, its
 * status as a word, drafts saying what they still need.
 *
 * Variables: $cards (arrays of area => Area, missing => string[]).
 */
$this->layout('layout', ['title' => 'Areas — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Areas</h2>
        <p>
            The places players wander: each one a scene, its search spots,
            and the table of what searching finds. Drafts are off the map
            until you publish.
        </p>
    </header>

    <div class="card admin-species__new">
        <h3>Start a new area</h3>
        <form method="post" action="/admin/areas/create" class="admin-species__new-form">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Its name</label>
                <input class="field__input" type="text" id="name" name="name" required
                       placeholder="e.g. The Starfall Shore">
            </div>
            <button class="btn btn--primary" type="submit">Start it (as a draft)</button>
        </form>
    </div>

    <ul class="admin-species__grid">
        <?php foreach ($cards as $card): ?>
            <?php $area = $card['area']; ?>
            <li>
                <a class="admin-species__card card" href="/admin/areas/<?= $this->e((string) $area->id) ?>">
                    <span class="admin-species__face">
                        <?php if ($area->backgroundUrl() !== null): ?>
                            <img src="<?= $this->e($area->backgroundUrl()) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span aria-hidden="true" class="admin-areas__glyph">&#127795;</span>
                        <?php endif; ?>
                    </span>
                    <span class="admin-species__name">
                        <?= $this->e($area->name) ?>
                        <span class="admin-audit__subject"><?= count($area->spots) ?> spot<?= count($area->spots) === 1 ? '' : 's' ?></span>
                    </span>
                    <span class="admin-species__status admin-species__status--<?= $area->isPublished() ? 'published' : 'draft' ?>">
                        <?= $area->isPublished() ? 'published' : 'draft' ?>
                    </span>
                    <?php if ($card['missing'] !== []): ?>
                        <span class="admin-species__missing">
                            still needs: <?= $this->e(implode(', ', $card['missing'])) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
