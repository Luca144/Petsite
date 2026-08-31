<?php
/**
 * One growth stage's card on the species editor: its picture and its words.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/admin-creature-edit.php, once per stage from config —
 * a fourth stage added there becomes a fourth card with no template change.
 *
 * Variables: $species (Species), $stage (string).
 */

$artUrl = $species->spriteUrlFor($stage);
// Honest about the source: a linked art-store picture, repo art carried by
// the old convention, or nothing yet.
$hasLinked = $species->spriteArtIdFor($stage) !== null;
$hasConvention = !$hasLinked && is_file(dirname(__DIR__, 2) . '/public' . $species->conventionPathFor($stage));
?>
<div class="card admin-stage">
    <h3>The <?= $this->e($stage) ?> stage</h3>

    <div class="admin-stage__row">
        <span class="admin-stage__art">
            <?php if ($hasLinked || $hasConvention): ?>
                <img class="pixelated" src="<?= $this->e($artUrl) ?>"
                     alt="<?= $this->e($species->name . ' as a ' . $stage) ?>" loading="lazy">
            <?php else: ?>
                <span class="admin-stage__none">no picture yet</span>
            <?php endif; ?>
        </span>
        <div class="admin-stage__side">
            <a class="btn btn--secondary" href="/admin/creatures/<?= $this->e((string) $species->id) ?>/picture/<?= $this->e(rawurlencode($stage)) ?>">
                Choose a picture
            </a>
            <?php if ($hasConvention): ?>
                <p class="field__hint">Using the original built-in art. Linking a picture from the art store replaces it everywhere at once.</p>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" action="/admin/creatures/<?= $this->e((string) $species->id) ?>/stage/<?= $this->e(rawurlencode($stage)) ?>">
        <?= $this->csrf_field() ?>
        <div class="field">
            <label class="field__label" for="blurb-<?= $this->e($stage) ?>">Words for this stage (optional — shown instead of the flavour text)</label>
            <textarea class="field__input" id="blurb-<?= $this->e($stage) ?>" name="blurb" rows="2"><?= $this->e((string) $species->blurbFor($stage)) ?></textarea>
        </div>
        <button class="btn btn--primary" type="submit">Save the <?= $this->e($stage) ?> words</button>
    </form>
</div>
