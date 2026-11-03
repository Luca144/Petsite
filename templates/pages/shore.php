<?php
/**
 * The shore (M6.5): set a message in a bottle adrift, and walk the
 * tideline for one that someone else sent.
 *
 * @package Felkyo\Templates
 *
 * Rendered by PostOfficeController::shore. Nobody chooses who finds their
 * bottle, and nobody chooses whose they find — there is deliberately no
 * list of bottles to browse.
 *
 * Variables: $messages (key => words), $canSend, $canFind (bool),
 * $adrift (int), $found (list: sender_id, sender, words, found_at).
 */
$this->layout('layout', ['title' => 'The shore — Felkyo Creatures']);
?>

<div class="shore-sea" aria-hidden="true"><span class="shore-sea__bottle">&#127870;</span></div>

<h2 class="panel-label">The shore</h2>
<p>Bottles drift in from all over Felkyo. Once a day you can send one out, and once a day you can look for one.</p>

<section class="card" aria-labelledby="shore-find">
    <h3 id="shore-find">Walk the tideline</h3>
    <?php if ($canFind): ?>
        <form method="post" action="/shore/find">
            <?= $this->csrf_field() ?>
            <button class="btn btn--primary" type="submit">Look for a bottle</button>
        </form>
    <?php else: ?>
        <p>You've walked the shore today. The tide turns at midnight.</p>
    <?php endif; ?>
</section>

<section class="card" aria-labelledby="shore-send">
    <h3 id="shore-send">Send a bottle</h3>
    <?php if ($adrift > 0): ?>
        <p class="library-hint"><?= $this->e((string) $adrift) ?> of your bottles <?= $adrift === 1 ? 'is' : 'are' ?> still out on the water.</p>
    <?php endif; ?>
    <?php if ($canSend): ?>
        <form method="post" action="/shore/send">
            <?= $this->csrf_field() ?>
            <fieldset class="shore-messages">
                <legend class="field__label">Choose your message</legend>
                <?php $first = true; foreach ($messages as $key => $words): ?>
                    <label class="shore-message">
                        <input type="radio" name="message" value="<?= $this->e($key) ?>" <?= $first ? 'checked' : '' ?>>
                        <span><?= $this->e($words) ?></span>
                    </label>
                <?php $first = false; endforeach; ?>
            </fieldset>
            <button class="btn btn--primary" type="submit">Seal it and set it adrift</button>
        </form>
    <?php else: ?>
        <p>You've sent today's bottle. Another tomorrow!</p>
    <?php endif; ?>
</section>

<?php if ($found !== []): ?>
    <section aria-labelledby="shore-found">
        <h3 id="shore-found" class="library-section">Bottles you've found</h3>
        <ul class="shore-found">
            <?php foreach ($found as $bottle): ?>
                <li class="shore-found__note card">
                    <p class="shore-found__words">&ldquo;<?= $this->e($bottle['words']) ?>&rdquo;</p>
                    <p class="shore-found__from">
                        from <a href="/player/<?= $this->e(rawurlencode($bottle['sender'])) ?>"><?= $this->e($bottle['sender']) ?></a>
                    </p>
                    <div class="shore-found__actions">
                        <a class="btn btn--secondary" href="/post-office?to=<?= $this->e(rawurlencode($bottle['sender'])) ?>">Send a card back</a>
                        <form method="post" action="/player/<?= $this->e(rawurlencode($bottle['sender'])) ?>/friend">
                            <?= $this->csrf_field() ?>
                            <button class="btn btn--secondary" type="submit">Add to friends</button>
                        </form>
                        <form method="post" action="/player/<?= $this->e(rawurlencode($bottle['sender'])) ?>/block">
                            <?= $this->csrf_field() ?>
                            <button class="btn--quiet" type="submit">block <?= $this->e($bottle['sender']) ?></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
