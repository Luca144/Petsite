<?php
/**
 * The keepsake — your favourite creature, on every page, with something to do.
 *
 * @package Felkyo\Templates
 *
 * Inserted by the sidebar when a player has chosen a favourite creature.
 * Variables: $summary (a creature summary with 'creature', 'species', 'stage',
 * 'mood'), $treats (OwnedItemStack[]), $currencyName.
 *
 * WHAT THIS IS FOR, AND WHY IT IS NOT JUST A LINK. A creature you have to go and
 * visit is a page. A creature that is already there, doing something, is a
 * companion — and the difference is entirely in whether you can reach out and
 * touch it without leaving where you are. That is the whole feature: two buttons
 * and a picture, on every screen.
 *
 * WHY THE TREAT CHOOSER IS RADIO CARDS AND NOT A DROPDOWN: because dropdowns are
 * forbidden (CLAUDE.md section 8), and because with two or three treats a
 * dropdown would hide the choice behind a tap for no gain. It also means each
 * treat can show what it DOES, which is the interesting part.
 *
 * KEPT SMALL ON PURPOSE. Everything here is one glance and one tap. Anything that
 * needs reading belongs on the creature's own page, which is one tap away.
 */

$creature = $summary['creature'];
$mood = $summary['mood'];
$species = $summary['species'];
$treats = $treats ?? [];

// Same image convention as the creature page and the collection card: the
// species slug and the creature's current life stage.
$image = '/assets/creatures/' . rawurlencode($species?->slug ?? '')
    . '/' . rawurlencode($summary['stage']) . '.gif';
?>
<section class="keepsake" aria-labelledby="keepsake-heading">
    <h2 class="keepsake__heading" id="keepsake-heading">your favourite</h2>

    <a class="keepsake__portrait" href="/creature/<?= $this->e((string) $creature->id) ?>">
        <?php /* The art is animated and pixel-drawn, so it keeps image-rendering:
                 pixelated — unlike avatars, whose thin lines vanish under it. The
                 alt carries the creature's name and how it is feeling, because a
                 screen reader user should get the same glance everybody else does. */ ?>
        <img class="keepsake__img pixelated<?= $mood->isResting ? ' keepsake__img--resting' : '' ?>"
             src="<?= $this->e($image) ?>"
             alt="<?= $this->e($creature->name . ', ' . $mood->word) ?>"
             width="96" height="96">
        <span class="keepsake__name"><?= $this->e($creature->name) ?></span>
    </a>

    <p class="keepsake__mood"><?= $this->e($mood->sentence($creature->name)) ?></p>

    <?= $this->insert('partials/mood-bars', ['mood' => $mood, 'justPetted' => false]) ?>

    <div class="keepsake__actions">
        <?php /* Petting your own creature earns no gems (you cannot pay yourself),
                 and that is fine — this button is not about earning. It is the
                 five-second "hello" that makes the card worth having. */ ?>
        <form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/pet">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="from" value="home">
            <button class="btn btn--secondary keepsake__btn" type="submit">
                <span aria-hidden="true">&hearts;</span> pet
            </button>
        </form>

        <a class="btn btn--secondary keepsake__btn"
           href="/creature/<?= $this->e((string) $creature->id) ?>">visit</a>
    </div>

    <?php if (!empty($treats)): ?>
        <form class="keepsake__feed" method="post"
              action="/creature/<?= $this->e((string) $creature->id) ?>/feed">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="from" value="home">

            <fieldset class="keepsake__treats">
                <legend class="keepsake__treats-legend">give a treat</legend>
                <?php foreach ($treats as $index => $stack): ?>
                    <label class="keepsake__treat">
                        <input type="radio" name="item_id"
                               value="<?= $this->e((string) $stack->item->id) ?>"
                               <?= $index === 0 ? 'checked' : '' ?>>
                        <span class="keepsake__treat-name">
                            <?= $this->e($stack->item->name) ?>
                            <span class="keepsake__treat-count" aria-hidden="true">&times;<?= $this->e((string) $stack->quantity) ?></span>
                            <span class="visually-hidden">, <?= $this->e((string) $stack->quantity) ?> owned</span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <button class="btn btn--secondary keepsake__btn" type="submit">feed</button>
        </form>
    <?php else: ?>
        <?php /* Never a dead end (golden rule 3): an empty satchel says where
                 treats come from and links there, rather than simply not offering
                 the button and leaving somebody to wonder. */ ?>
        <p class="keepsake__no-treats">
            No treats left. <a href="/shop">The village store</a> sells them,
            and they turn up while <a href="/explore">exploring</a>.
        </p>
    <?php endif; ?>
</section>
