<?php
/**
 * THE page: one species, everything about it, edited in place.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminCreatureEditController (Artist role). Each section is its
 * own small form — save as you go. The stage cards and the settings live in
 * partials to keep this file inside its size limit; together they are still
 * ONE page, which is the whole point (build plan M2.3).
 *
 * Variables: $species (Species), $stageNames (string[]), $missing (string[]),
 * $treats (Item[]), $versions (arrays from SpeciesVersionRepository::listFor).
 */
$this->layout('layout', ['title' => $this->e($species->name) . ' — Creatures — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($species->name) ?></h2>
        <p class="admin-species__statusline">
            <span class="admin-species__status admin-species__status--<?= $species->isPublished() ? 'published' : 'draft' ?>">
                <?= $species->isPublished() ? 'published' : 'draft' ?>
            </span>
            <?php if (!$species->isPublished() && $missing !== []): ?>
                <span class="admin-species__missing">still needs: <?= $this->e(implode(', ', $missing)) ?></span>
            <?php endif; ?>
        </p>

        <div class="admin-species__actions">
            <a class="btn btn--secondary" href="/admin/creatures/<?= $this->e((string) $species->id) ?>/preview">
                Preview on the real page
            </a>
            <a class="btn btn--secondary" href="/admin/creatures/<?= $this->e((string) $species->id) ?>/forms">
                Alternate forms
            </a>
            <?php if ($species->isPublished()): ?>
                <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/unpublish">
                    <?= $this->csrf_field() ?>
                    <?php /* Plain words about what the button DOES, on the button's
                             own card — nobody should have to guess (golden rule 1). */ ?>
                    <button class="btn btn--secondary" type="submit">Back to draft</button>
                </form>
                <p class="field__hint">Back to draft: creatures people already have keep living; nobody new can get one.</p>
            <?php else: ?>
                <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/publish">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--primary" type="submit">Publish</button>
                </form>
                <a class="btn--quiet" href="/admin/creatures/<?= $this->e((string) $species->id) ?>/delete">delete this species&hellip;</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="card">
        <h3>Names &amp; words</h3>
        <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/words">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Name</label>
                <input class="field__input" type="text" id="name" name="name"
                       value="<?= $this->e($species->name) ?>" required>
            </div>
            <div class="field">
                <label class="field__label" for="flavour_text">Flavour text (shown on its page and in the shop)</label>
                <textarea class="field__input" id="flavour_text" name="flavour_text" rows="2"><?= $this->e((string) $species->flavourText) ?></textarea>
            </div>
            <div class="field">
                <label class="field__label" for="lore">Lore — the story in its library book (leave a blank line between paragraphs)</label>
                <textarea class="field__input" id="lore" name="lore" rows="5"><?= $this->e((string) $species->lore) ?></textarea>
            </div>
            <button class="btn btn--primary" type="submit">Save the words</button>
        </form>
    </div>

    <?php foreach ($stageNames as $stage): ?>
        <?= $this->insert('partials/admin-stage-card', ['species' => $species, 'stage' => $stage]) ?>
    <?php endforeach; ?>

    <div class="card admin-stage">
        <h3>Library picture</h3>
        <div class="admin-stage__row">
            <span class="admin-stage__art">
                <?php if ($species->libraryArtUrl() !== null): ?>
                    <img class="pixelated" src="<?= $this->e($species->libraryArtUrl()) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <span class="admin-stage__none">none yet</span>
                <?php endif; ?>
            </span>
            <a class="btn btn--secondary" href="/admin/creatures/<?= $this->e((string) $species->id) ?>/picture/library">
                Choose a picture
            </a>
        </div>
    </div>

    <?= $this->insert('partials/admin-species-settings', ['species' => $species, 'treats' => $treats]) ?>

    <div class="card">
        <h3>Versions</h3>
        <p class="field__hint">Every save is kept (newest 20). Restoring never touches whether the species is published.</p>
        <?php if (empty($versions)): ?>
            <p>No history yet — it starts with the first save.</p>
        <?php else: ?>
            <ul class="admin-species__versions">
                <?php foreach ($versions as $version): ?>
                    <li class="admin-species__version">
                        <span class="admin-species__version-when"><?= $this->e($version['created_at']) ?></span>
                        <span>by <?= $this->e($version['saved_by'] ?? 'the site') ?></span>
                        <span>as &ldquo;<?= $this->e((string) ($version['snapshot']['name'] ?? '?')) ?>&rdquo;</span>
                        <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/revert">
                            <?= $this->csrf_field() ?>
                            <input type="hidden" name="version_id" value="<?= $this->e((string) $version['id']) ?>">
                            <button type="submit" class="btn--quiet">restore this</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
