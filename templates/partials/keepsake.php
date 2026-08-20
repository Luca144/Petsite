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

use Felkyo\Design\PixelArt;

$creature = $summary['creature'];
$mood = $summary['mood'];
$species = $summary['species'];
$treats = $treats ?? [];

// Same image convention as the creature page and the collection card: the
// species slug and the creature's current life stage.
$image = '/assets/creatures/' . rawurlencode($species?->slug ?? '')
    . '/' . rawurlencode($summary['stage']) . '.gif';

// The largest WHOLE multiple of the sprite's native pixels that fits its frame —
// a fractional scale smears pixel art (see PixelArt).
[$artWidth, $artHeight] = PixelArt::displaySize($image, 104);
?>
<section class="keepsake" aria-labelledby="keepsake-heading">
    <h2 class="keepsake__heading" id="keepsake-heading">your favourite</h2>

    <?php /* THE PORTRAIT IS THE DOOR TO THE CREATURE'S PAGE — the card used to
             also carry a "visit X" text link underneath, and the Product Owner
             rightly pointed out that people know they can click the pet. One
             door, and it is the biggest thing on the card. */ ?>
    <a class="keepsake__portrait" href="/creature/<?= $this->e((string) $creature->id) ?>">
        <?php /* The art is animated and pixel-drawn, so it keeps image-rendering:
                 pixelated — unlike avatars, whose thin lines vanish under it. The
                 alt carries the creature's name and how it is feeling, because a
                 screen reader user should get the same glance everybody else does. */ ?>
        <span class="keepsake__art">
            <img class="keepsake__img pixelated<?= $mood->isResting ? ' keepsake__img--resting' : '' ?>"
                 src="<?= $this->e($image) ?>"
                 alt="<?= $this->e($creature->name . ', ' . $mood->word) ?>"
                 width="<?= $this->e((string) $artWidth) ?>" height="<?= $this->e((string) $artHeight) ?>">
        </span>
        <span class="keepsake__name"><?= $this->e($creature->name) ?></span>
    </a>

    <p class="keepsake__mood" role="status"><?= $this->e($mood->sentence($creature->name)) ?></p>

    <?php /* $justPetted comes from the layout's shared data, so the card celebrates
             when the thing that just happened happened HERE. It used to be hard-coded
             false, which meant pressing pet or feed on the card changed the bar's
             width silently — the one action with no visible answer on the whole
             site (golden rule 4). */ ?>
    <?= $this->insert('partials/mood-bars', [
        'mood' => $mood,
        'justPetted' => $justPetted ?? false,
    ]) ?>

    <?php /* THREE BUTTONS IN A ROW, AND NOTHING ELSE.

             This used to be two buttons plus a list of radio cards, one per treat.
             That made the card the tallest thing in the sidebar and pushed the whole
             column past the height of a laptop screen — so the card itself became
             the part you had to scroll to the bottom of the page to reach, which is
             the opposite of what a card on every page is for.

             A list was the wrong shape here anyway. The card's whole promise is one
             tap; picking a particular treat is something you go to the creature's
             own page to do, and the full chooser lives there. "feed" here means
             "give them something nice", and the SERVER chooses — the favourite if
             you have one (see FeedingService::bestTreatFor). */ ?>
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

        <?php /* No item_id: the server picks. When the satchel is empty it says so
                 and names where treats come from, rather than the button quietly
                 not being here (golden rule 3). */ ?>
        <form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/feed">
            <?= $this->csrf_field() ?>
            <input type="hidden" name="from" value="home">
            <button class="btn btn--secondary keepsake__btn" type="submit">
                <span aria-hidden="true">&#9679;</span> feed
                <?php if (!empty($treats)): ?>
                    <span class="visually-hidden">
                        &mdash; <?= $this->e((string) count($treats)) ?> kinds of treat to hand
                    </span>
                <?php endif; ?>
            </button>
        </form>

        <?php /* A game. A LINK, not a form, because starting one changes nothing —
                 the round is opened by the page you land on. The server keeps the
                 answer, which is why the result can be trusted (see PlayableGames). */ ?>
        <a class="btn btn--secondary keepsake__btn"
           href="/creature/<?= $this->e((string) $creature->id) ?>/play">
            <span aria-hidden="true">&#9670;</span> play
        </a>
    </div>

</section>
