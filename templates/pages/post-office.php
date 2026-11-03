<?php
/**
 * The post office (M6.4): choose a card, choose who, send.
 *
 * @package Felkyo\Templates
 *
 * Rendered by PostOfficeController::postOffice. No dropdowns (CLAUDE.md §9):
 * cards are a grid of tappable radio tiles; the recipient is typed, or
 * tapped from your friends (which just fills in the name).
 *
 * The card's words are printed by the site over the art (or on a plain card
 * while there's no art yet), never drawn into it — so they stay sharp,
 * editable and readable aloud.
 *
 * Variables: $designs (list of designs), $friends, $to (prefilled name).
 */
$this->layout('layout', ['title' => 'The post office — Felkyo Creatures']);
?>

<h2 class="panel-label">The post office</h2>
<p>Send a card to anyone in Felkyo. It goes up on their mantelpiece for everyone to see. The words are already written &mdash; just pick the one that fits.</p>

<?php if ($designs === []): ?>
    <p class="empty-state">The card rack is empty today.</p>
<?php else: ?>
    <form method="post" action="/post-office">
        <?= $this->csrf_field() ?>
        <fieldset class="postcard-rack">
            <legend class="field__label">1. Choose a card</legend>
            <?php foreach ($designs as $index => $design): ?>
                <label class="postcard-tile">
                    <input type="radio" name="design_id" value="<?= $this->e((string) $design['id']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                    <?= $this->insert('partials/postcard', ['card' => $design]) ?>
                    <span class="postcard-tile__price"><?= $this->e($design['name']) ?> &middot; <?= $this->e((string) $design['gem_price']) ?> gems</span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <div class="field">
            <label class="field__label" for="recipient">2. Who is it for? (their name)</label>
            <input class="field__input" type="text" id="recipient" name="recipient" maxlength="40" required
                   value="<?= $this->e($to) ?>" autocomplete="off">
        </div>

        <?php if ($friends !== []): ?>
            <?php /* Tapping a friend fills the name in. Plain links that work
                     without scripting: they reload this page with ?to=. */ ?>
            <p class="field__hint">Or tap a friend:
                <?php foreach ($friends as $friend): ?>
                    <a class="postcard-friend" href="/post-office?to=<?= $this->e(rawurlencode($friend['username'])) ?>"><?= $this->e($friend['username']) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <button class="btn btn--primary" type="submit">3. Send it</button>
    </form>
<?php endif; ?>
