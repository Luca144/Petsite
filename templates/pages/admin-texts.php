<?php
/**
 * The panel's list of the site's words: every sentence on every page, and
 * every message the site sends, each with a box to change it.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminTextsController::index (artist role). Pick a page from
 * the pills or search; with neither, it lists only what has been changed —
 * the useful "what have we touched?" view. Each sentence is its own small
 * form, so one Save changes one sentence and says so.
 *
 * Variables: $places (string[]), $where (string), $search (string),
 * $rows (arrays: key, where, original, words, changed), $total (int),
 * $isEditing (bool).
 */
$this->layout('layout', ['title' => 'The site\'s words — Felkyo Creatures']);
$here = '/admin/texts?where=' . rawurlencode($where) . '&q=' . rawurlencode($search);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>The site&rsquo;s words</h2>
        <p>Every sentence on the site, and every message it sends. Change any of them here &mdash; or
           switch on <b>editing mode</b> and click the words right where they are on the page.</p>
        <p class="library-hint">Use **two stars** for <b>bold</b> and *one star* for <em>slanted</em>.
           Gaps like <code>{name}</code> or <code>{1}</code> are filled in on the day (a name, a number) &mdash;
           move them or leave them out, but don&rsquo;t invent new ones.</p>
        <form method="post" action="/admin/texts/mode">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="back" value="/">
            <button class="btn <?= $isEditing ? 'btn--secondary' : 'btn--primary' ?>" type="submit">
                <?= $isEditing ? 'Switch editing mode off' : 'Switch on editing mode, and go to the site' ?>
            </button>
        </form>
    </header>

    <form class="card texts-search" method="get" action="/admin/texts">
        <input type="hidden" name="where" value="<?= $this->e($where) ?>">
        <label class="field__label" for="texts-q">Find words</label>
        <div class="texts-search__row">
            <input class="field__input" type="search" id="texts-q" name="q" value="<?= $this->e($search) ?>" placeholder="e.g. kettle">
            <button class="btn btn--secondary" type="submit">Search</button>
        </div>
    </form>

    <nav class="texts-places" aria-label="Pages">
        <a class="texts-places__pill" href="/admin/texts"<?= $where === '' ? ' aria-current="page"' : '' ?>>changed so far</a>
        <?php foreach ($places as $place): ?>
            <a class="texts-places__pill" href="/admin/texts?where=<?= $this->e(rawurlencode($place)) ?>"<?= $where === $place ? ' aria-current="page"' : '' ?>><?= $this->e($place) ?></a>
        <?php endforeach; ?>
    </nav>

    <p class="library-hint"><?= $this->e(count($rows) . ' shown of ' . $total . ' sentences.') ?></p>
    <?php if ($rows === []): ?>
        <div class="card"><p class="empty-state">
            <?= $where === '' && $search === '' ? 'Nothing has been changed yet — pick a page above to start.' : 'No sentences match.' ?>
        </p></div>
    <?php endif; ?>

    <?php foreach ($rows as $row): ?>
        <form class="card texts-row" method="post" action="/admin/texts/save">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="key" value="<?= $this->e($row['key']) ?>">
            <input type="hidden" name="back" value="<?= $this->e($here) ?>">
            <label class="field__label" for="text-<?= $this->e(md5($row['key'])) ?>">
                <?= $this->e($row['where']) ?><?= $row['changed'] ? ' · changed' : '' ?>
            </label>
            <textarea class="field__input texts-row__words" id="text-<?= $this->e(md5($row['key'])) ?>" name="words"
                      rows="2" maxlength="1000"><?= $this->e($row['words']) ?></textarea>
            <?php if ($row['changed']): ?>
                <p class="texts-row__original">Originally: &ldquo;<?= $this->e($row['original']) ?>&rdquo;</p>
            <?php endif; ?>
            <div class="button-row">
                <button class="btn btn--primary" type="submit">Save</button>
                <?php if ($row['changed']): ?>
                    <button class="btn btn--secondary" type="submit" name="reset" value="1">Back to the original</button>
                <?php endif; ?>
            </div>
        </form>
    <?php endforeach; ?>
</section>
