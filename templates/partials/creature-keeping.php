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
    <h2 id="creature-keeping" class="panel-label"><?= $this->t('creature-keeping.keeping', 'Keeping {name}', ['name' => $creature->name]) ?></h2>

    <div>
        <?php if ($creature->isFrozen): ?>
            <p><?= $this->t('creature-keeping.is-staying-a-it-still', '{name} is staying a {stage}. It still learns and earns as usual.', ['name' => $creature->name, 'stage' => $stage]) ?></p>
            <form method="post" action="/creature/<?= $this->e($id) ?>/unfreeze">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit"><?= $this->t('creature-keeping.let-it-grow-again', 'Let it grow again') ?></button>
            </form>
        <?php else: ?>
            <p><?= $this->t('creature-keeping.love-just-as-it-is', 'Love {name} just as it is? You can keep it a {stage} for as long as you like — nothing is lost, and it catches up gently when you let it grow.', ['name' => $creature->name, 'stage' => $stage]) ?></p>
            <form method="post" action="/creature/<?= $this->e($id) ?>/freeze">
                <?= $this->csrf_field() ?>
                <button class="btn btn--secondary" type="submit"><?= $this->t('creature-keeping.keep-it-a', 'Keep it a {stage}', ['stage' => $stage]) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <details>
        <summary><?= $this->t('creature-keeping.find-a-new-home', 'Find {name} a new home', ['name' => $creature->name]) ?></summary>
        <p>
            <?= $this->t('creature-keeping.if-you-can-t-look', 'If you can\'t look after {name} any more, the pound will. For the first days you can still take it back; after that, someone else can adopt it — and you can\'t.', ['name' => $creature->name]) ?>
        </p>
        <form method="post" action="/creature/<?= $this->e($id) ?>/to-pound">
            <?= $this->csrf_field() ?>
            <label class="creature-keeping__sure">
                <input type="checkbox" name="sure" value="yes" required>
                <?= $this->t('creature-keeping.i-m-sure', 'I\'m sure') ?>
            </label>
            <button class="btn btn--secondary" type="submit"><?= $this->t('creature-keeping.take-to-the-pound', 'Take {name} to the pound', ['name' => $creature->name]) ?></button>
        </form>
    </details>
</section>
