<?php
/**
 * The panel's alternate-forms page for one species (M5.3).
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminFormsController::show. One card per form: its words, a
 * picture per stage (each a link to the shared picker), and publish /
 * draft / delete. A stage with no form picture shows the species' default
 * sprite, faded, with "default" written under it — so the artist can see
 * exactly what a player would see, and colour is never the only signal.
 *
 * Variables: $species (Species), $forms (SpeciesForm[], drafts included),
 * $stageNames (string[]).
 */
$this->layout('layout', ['title' => 'Forms — ' . $species->name . ' — Felkyo Creatures']);
$speciesId = (string) $species->id;
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Alternate forms of <?= $this->e($species->name) ?></h2>
        <p>
            A form is the same creature with a different look. Published forms show in the library,
            and every new <?= $this->e($species->name) ?> has a small chance of arriving in one.
            Stages you haven't drawn yet simply use the normal picture.
        </p>
        <p><a href="/admin/creatures/<?= $this->e($speciesId) ?>">&larr; back to <?= $this->e($species->name) ?></a></p>
    </header>

    <div class="card">
        <h3>Start a new form</h3>
        <form method="post" action="/admin/creatures/<?= $this->e($speciesId) ?>/forms/create">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="new-form-name">Its name (for example "Frost <?= $this->e($species->name) ?>")</label>
                <input class="field__input" type="text" id="new-form-name" name="name" maxlength="60" required>
            </div>
            <button class="btn btn--primary" type="submit">Start it as a draft</button>
        </form>
    </div>

    <?php if ($forms === []): ?>
        <p class="empty-state">No alternate forms yet.</p>
    <?php endif; ?>

    <?php foreach ($forms as $form): ?>
        <?php $formId = (string) $form->id; ?>
        <div class="card admin-form-card">
            <h3>
                <?= $this->e($form->name) ?>
                <span class="admin-species__status admin-species__status--<?= $form->isPublished() ? 'published' : 'draft' ?>">
                    <?= $form->isPublished() ? 'published' : 'draft' ?>
                </span>
            </h3>

            <ul class="admin-form-card__stages">
                <?php foreach ($stageNames as $stage): ?>
                    <?php $own = $form->ownSpriteUrlFor($stage); ?>
                    <li>
                        <a class="admin-form-card__stage" href="/admin/forms/<?= $this->e($formId) ?>/picture/<?= $this->e(rawurlencode($stage)) ?>">
                            <img class="pixelated<?= $own === null ? ' admin-form-card__fallback' : '' ?>"
                                 src="<?= $this->e($own ?? $species->spriteUrlFor($stage)) ?>" alt="" loading="lazy">
                            <span><?= $this->e($stage) ?><?= $own === null ? ' · default' : '' ?></span>
                            <span class="field__hint">choose a picture</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form method="post" action="/admin/forms/<?= $this->e($formId) ?>/words">
                <?= $this->csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="form-name-<?= $this->e($formId) ?>">Name</label>
                    <input class="field__input" type="text" id="form-name-<?= $this->e($formId) ?>" name="name"
                           value="<?= $this->e($form->name) ?>" maxlength="60" required>
                </div>
                <div class="field">
                    <label class="field__label" for="form-blurb-<?= $this->e($formId) ?>">A few words about it (shown in the library)</label>
                    <textarea class="field__input" id="form-blurb-<?= $this->e($formId) ?>" name="blurb" rows="2"><?= $this->e((string) $form->blurb) ?></textarea>
                </div>
                <button class="btn btn--primary" type="submit">Save the words</button>
            </form>

            <div class="admin-species__actions">
                <form method="post" action="/admin/forms/<?= $this->e($formId) ?>/<?= $form->isPublished() ? 'unpublish' : 'publish' ?>">
                    <?= $this->csrf_field() ?>
                    <button class="btn <?= $form->isPublished() ? 'btn--secondary' : 'btn--primary' ?>" type="submit">
                        <?= $form->isPublished() ? 'Back to draft' : 'Publish' ?>
                    </button>
                </form>
                <?php /* A browser confirm is the cheap "are you sure" here; the
                         harm is also small and recoverable: creatures wearing
                         the form simply look like their species again. */ ?>
                <form method="post" action="/admin/forms/<?= $this->e($formId) ?>/delete"
                      onsubmit="return confirm('Delete this form? Creatures wearing it will look like normal <?= $this->e($species->name) ?>s again.');">
                    <?= $this->csrf_field() ?>
                    <button class="btn--quiet" type="submit">delete this form</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</section>
