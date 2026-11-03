<?php
/**
 * One postcard face: the art (if the artist has drawn it) with the card's
 * words set on top by the site.
 *
 * @package Felkyo\Templates
 *
 * Used by the post office rack and the mantelpiece on profiles.
 * Variable: $card (array with 'message' and 'art_name').
 */
$artUrl = !empty($card['art_name']) ? '/art/' . $card['art_name'] : null;
?>
<span class="postcard<?= $artUrl === null ? ' postcard--plain' : '' ?>">
    <?php if ($artUrl !== null): ?>
        <img class="postcard__art" src="<?= $this->e($artUrl) ?>" alt="" loading="lazy">
    <?php endif; ?>
    <span class="postcard__words"><?= $this->e($card['message']) ?></span>
</span>
