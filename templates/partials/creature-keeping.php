<?php
/**
 * The keeper's corner of a creature page (M3): keep it at its stage or let
 * it grow, and — the big step — take it to the pound.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/creature.php for the OWNER only. (The server checks
 * ownership again on every action; hiding the forms is a courtesy.)
 *
 * The pound form needs a ticked "I'm sure" box, checked on the server, so a
 * stray tap can't send anybody away — and the grace period means even a
 * sure tap can be undone for a few days (golden rule 9).
 *
 * Variables: $creature (Creature), $stage (string).
 */
$id = (string) $creature->id;
?>
<section class="card creature-keeping" aria-labelledby="creature-keeping">
    <h2 id="creature-keeping" class="panel-label">Keeping <?= $this->e($creature->name) ?></h2>

    <div>
        <?php if ($creature->isFrozen): ?>
            <p><?= $this->e($creature->name) ?> is staying a <?= $this->e($stage) ?>. It still learns and earns as usual.</p>
            <form method="post" action="/creature/<?= $this->e($id) ?>/unfreeze">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit">Let it grow again</button>
            </form>
        <?php else: ?>
            <p>Love <?= $this->e($creature->name) ?> just as it is? You can keep it a <?= $this->e($stage) ?> for as long as you like &mdash; nothing is lost, and it catches up gently when you let it grow.</p>
            <form method="post" action="/creature/<?= $this->e($id) ?>/freeze">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit">Keep it a <?= $this->e($stage) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <details>
        <summary>Find <?= $this->e($creature->name) ?> a new home</summary>
        <p>
            If you can't look after <?= $this->e($creature->name) ?> any more, the pound will.
            For the first days you can still take it back; after that, someone else can adopt it &mdash; and you can't.
        </p>
        <form method="post" action="/creature/<?= $this->e($id) ?>/to-pound">
            <?= $this->csrf_field() ?>
            <label class="creature-keeping__sure">
                <input type="checkbox" name="sure" value="yes" required>
                I'm sure
            </label>
            <button class="btn btn--secondary" type="submit">Take <?= $this->e($creature->name) ?> to the pound</button>
        </form>
    </details>
</section>
