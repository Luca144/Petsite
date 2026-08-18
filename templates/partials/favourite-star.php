<?php
/**
 * The star: make this creature a favourite, or stop.
 *
 * @package Felkyo\Templates
 *
 * Insert it with:
 *   $this->insert('partials/favourite-star', ['creature' => $creature, 'from' => 'collection']);
 *
 * $from says where the button was pressed, so the player is sent back there. It
 * is turned into an address by an ALLOW-LIST in FavouriteController, never used
 * as one directly.
 *
 * A REAL BUTTON IN A REAL FORM, not a link with an icon. Starring changes data,
 * so it is a POST with a CSRF token — a link that changed something could be
 * triggered by any page that could get a browser to load it.
 *
 * THE STAR IS NEVER ALONE. It always sits beside a word ("favourite" /
 * "favourited"), because an icon has to be learnt before it means anything, and
 * because the state must not ride on a shape or a colour alone (CLAUDE.md §8).
 * aria-pressed carries the same state for a screen reader, which is how somebody
 * who cannot see the fill still knows whether it is on.
 */

$isFavourite = $creature->isFeatured();
$from = $from ?? 'creature';
?>
<form class="favourite-star" method="post"
      action="/creature/<?= $this->e((string) $creature->id) ?>/favourite">
    <?= $this->csrf_field() ?>
    <input type="hidden" name="from" value="<?= $this->e($from) ?>">
    <button class="favourite-star__button<?= $isFavourite ? ' favourite-star__button--on' : '' ?>"
            type="submit"
            aria-pressed="<?= $isFavourite ? 'true' : 'false' ?>">
        <?php /* A filled star when it is one, a hollow one when it is not — two
                 different SHAPES, so the difference survives being printed in
                 black and white or seen by somebody who cannot tell the colours
                 apart. */ ?>
        <span class="favourite-star__glyph" aria-hidden="true"><?= $isFavourite ? '&#9733;' : '&#9734;' ?></span>
        <span class="favourite-star__word">
            <?= $isFavourite ? 'favourite' : 'make favourite' ?>
        </span>
    </button>
</form>
