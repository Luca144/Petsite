<?php
/**
 * The fine-tuning CSS box: small fixes to the site's look without a
 * developer, with preview, history and one-click restore.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminLookController::css (artist role). The flow is
 * deliberately "preview first": Preview shows the CSS to you alone on
 * every page (with a bar saying so), Publish shows it to everyone. Every
 * published version is kept, so a mistake at 3am is one press to undo.
 *
 * Variables: $css (the draft being previewed, or the live CSS),
 * $isPreviewing (bool), $history (arrays from CustomCssRepository::history),
 * $maxLength (int).
 */
$this->layout('layout', ['title' => 'Fine-tuning CSS — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Fine-tuning CSS</h2>
        <p>For small fixes to how the site looks &mdash; a gap, a size, a colour on one thing.
           Try it with <b>Preview</b> first: only you see it, on every page, until you publish
           or stop. Every published version is kept below, so nothing is ever lost.</p>
        <p><a href="/admin/look">&larr; Site look</a></p>
    </header>

    <?php if ($isPreviewing): ?>
        <div class="card look-css__previewing" role="status">
            <p><b>You&rsquo;re previewing.</b> Only you can see this CSS. Have a look around the site,
               then come back here to publish it or stop.</p>
            <form method="post" action="/admin/look/css/stop-preview">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit">Stop previewing</button>
            </form>
        </div>
    <?php endif; ?>

    <form class="card" method="post" action="/admin/look/css/publish">
        <?= $this->csrf_field() ?>
        <div class="field">
            <label class="field__label" for="look-css">The CSS</label>
            <textarea class="field__input look-css__box" id="look-css" name="css" rows="16" spellcheck="false"
                      maxlength="<?= $this->e((string) $maxLength) ?>" aria-describedby="look-css-help"><?= $this->e($css) ?></textarea>
            <p class="library-hint" id="look-css-help">
                Pictures must come from this site (<code>url(/art/...)</code>). Not allowed: the
                &ldquo;&lt;&rdquo; character, <code>@import</code>, backslash escapes.
                Colours from the theme work as <code>var(--purple)</code>, <code>var(--gold)</code> and so on.
            </p>
        </div>
        <div class="field">
            <label class="field__label" for="look-css-note">A note for the history (optional)</label>
            <input class="field__input" type="text" id="look-css-note" name="note" maxlength="120"
                   placeholder="e.g. bigger shop buttons on phones">
        </div>
        <div class="button-row">
            <button class="btn btn--secondary" type="submit" formaction="/admin/look/css/preview">Preview — just me</button>
            <button class="btn btn--primary" type="submit">Publish to everyone</button>
        </div>
    </form>

    <div class="card">
        <h3>History</h3>
        <?php if ($history === []): ?>
            <p class="empty-state">Nothing published yet.</p>
        <?php else: ?>
            <ol class="look-css__history">
                <?php foreach ($history as $index => $version): ?>
                    <li class="look-css__version">
                        <span>
                            <b>#<?= $this->e((string) $version['id']) ?></b>
                            <?= $index === 0 ? '<span class="look-css__live">live now</span>' : '' ?>
                            <?= $this->e($version['saved_at']) ?>
                            by <?= $this->e($version['saved_by'] ?? 'someone who has left') ?>
                            &middot; <?= $version['length'] === 0 ? 'switched off' : $this->e(number_format($version['length']) . ' characters') ?>
                            <?php if ($version['note'] !== ''): ?>&middot; &ldquo;<?= $this->e($version['note']) ?>&rdquo;<?php endif; ?>
                        </span>
                        <?php if ($index !== 0): ?>
                            <form method="post" action="/admin/look/css/restore/<?= $this->e((string) $version['id']) ?>">
                                <?= $this->csrf_field() ?>
                                <button class="btn btn--secondary" type="submit">Put this one back</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
