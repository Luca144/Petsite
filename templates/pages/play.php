<?php
/**
 * A game with your creature.
 *
 * @package Felkyo\Templates
 *
 * Rendered by PlayController::show(). Variables: $creature, $game (from
 * PlayableGames::presentation() — slug, name, prompt, choices).
 *
 * THE ANSWER IS NOT ON THIS PAGE, and there is nothing here that could reveal it.
 * The template is given the number of choices and their words; which one is right
 * is held in the session. That is the whole reason these are guessing games rather
 * than arcade games — see PlayableGames for the reasoning.
 *
 * NO JAVASCRIPT AT ALL. Three buttons in a form. The rustling is CSS, and the game
 * plays identically with scripting switched off — which is what CLAUDE.md section 8
 * asks for, and what makes the result trustworthy.
 *
 * EACH CHOICE IS ITS OWN SUBMIT BUTTON, not a radio group with a "go" button. One
 * tap instead of two, no chance of submitting with nothing chosen, and the thing
 * you press is the thing you mean.
 */
$this->layout('layout', ['title' => $game['name'] . ' with ' . $creature->name . ' — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $this->e($game['name']) ?></h2>

<section class="play play--<?= $this->e($game['slug']) ?>">
    <p class="play__prompt"><?= $this->e($game['prompt']) ?></p>

    <form class="play__choices" method="post"
          action="/creature/<?= $this->e((string) $creature->id) ?>/play">
        <?= $this->csrf_field() ?>

        <?php foreach ($game['choices'] as $index => $label): ?>
            <?php /* The value is the INDEX, and the server compares it against an
                     index it never sent — so there is nothing in this markup worth
                     tampering with. Changing the number just changes your guess. */ ?>
            <button class="play__choice" type="submit" name="choice"
                    value="<?= $this->e((string) $index) ?>">
                <span class="play__choice-art" aria-hidden="true"></span>
                <span class="play__choice-label"><?= $this->e($label) ?></span>
            </button>
        <?php endforeach; ?>
    </form>

    <p class="play__note">
        <?php /* Said plainly and up front, because a game whose stakes are unclear
                 makes people cautious about playing it. Golden rules 4 and 11. */ ?>
        Guess wrong and <?= $this->e($creature->name) ?> still has a lovely time —
        you just cheer them up a little less.
    </p>

    <p class="play__away">
        <a href="/creature/<?= $this->e((string) $creature->id) ?>">
            back to <?= $this->e($creature->name) ?>
        </a>
    </p>
</section>
