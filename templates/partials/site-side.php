<?php
/**
 * The sidebar — everything that is YOURS, in one parchment panel.
 *
 * @package Felkyo\Templates
 *
 * Inserted once by layout.php, before the main panel. On a phone it renders
 * first as a compact identity strip; from 900px up it becomes the left column
 * the artist drew (see sidebar.css).
 *
 * WHAT LIVES HERE AND WHY: the 2026-08-14 redesign splits navigation into
 * "you" (this sidebar) and "the world" (the bar under the banner). Your name,
 * your purse, your avatar — which is the door to your own page — the links to
 * your things, the way out, and your favourite creature as a keepsake card.
 *
 * Variables, all provided by the layout: $currentUser, $currentPath,
 * $currencyName, $registrationOpen, $currentAvatarPath, $currentAvatarName,
 * $favouriteSummary (a creature summary array, or null when no favourite is
 * chosen — then the keepsake slot simply stays empty).
 */

/* Which link is the current page. Home is an exact match (every path starts
   with "/"); the others match themselves and anything beneath them. */
$currentPath = $currentPath ?? '';
$isCurrent = static function (string $href) use ($currentPath): bool {
    if ($href === '/') {
        return $currentPath === '/';
    }

    return $currentPath === $href || str_starts_with($currentPath, $href);
};
?>
<aside class="site-side" aria-label="You">
    <?php if (!empty($currentUser)): ?>
        <div class="site-side__who">
            <p class="site-side__name"><?= $this->e($currentUser->username) ?></p>

            <?php /* The purse, visible without scrolling. "Can I afford this?"
                     is asked before any page is scrolled, so the answer sits at
                     the top — a quiet status chip, deliberately not shaped like
                     a button (an earlier version looked pressable and did
                     nothing). The diamond is decoration; the words carry it. */ ?>
            <p class="site-purse">
                <span aria-hidden="true">&#9670;</span>
                <span class="visually-hidden">You&rsquo;re carrying</span>
                <?= $this->e((string) $currentUser->currencyBalance) ?>
                <?= $this->e($currencyName ?? 'coins') ?>
            </p>
        </div>

        <?php if (!empty($currentAvatarPath)): ?>
            <?php /* Your portrait is the door to your own page, and says so in
                     words underneath — an icon on its own has to be learnt.
                     The image's alt is empty on purpose: "my page" is the whole
                     accessible name of this link, so a screen reader hears the
                     destination once, not the avatar's title as well. */ ?>
            <a class="site-side__portrait"
               href="/player/<?= $this->e(rawurlencode($currentUser->username)) ?>"<?=
                $isCurrent('/player/' . rawurlencode($currentUser->username)) || $isCurrent('/profile')
                    ? ' aria-current="page"' : '' ?>>
                <img class="site-side__portrait-img pixelated"
                     src="<?= $this->e($currentAvatarPath) ?>"
                     alt="" width="96" height="96">
                <span class="site-side__portrait-word">my page</span>
            </a>
        <?php endif; ?>

        <nav class="site-side__nav" aria-label="Your pages">
            <ul class="site-side__links">
                <li><a href="/"<?= $isCurrent('/') ? ' aria-current="page"' : '' ?>>home</a></li>
                <li><a href="/creatures"<?= $isCurrent('/creatures') ? ' aria-current="page"' : '' ?>>my creatures</a></li>
                <li><a href="/inventory"<?= $isCurrent('/inventory') ? ' aria-current="page"' : '' ?>>inventory</a></li>
            </ul>
        </nav>

        <?php /* The way out — deliberately the quietest control here. Log out is
                 not a destination, so it is a plain word, not a pill. */ ?>
        <form method="post" action="/logout" class="site-side__logout">
            <?= $this->csrf_field() ?>
            <button type="submit">log out</button>
        </form>

        <?php if (!empty($favouriteSummary)): ?>
            <?php /* The keepsake: your favourite creature, in the same card the
                     collection uses (gold edge, star, the word "favourite").
                     Desktop-only by stylesheet — on a phone it would cost scroll
                     height, and the same creature is one tap away on home. */ ?>
            <div class="site-side__favourite">
                <?= $this->insert('partials/creature-card', ['summary' => $favouriteSummary]) ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="site-side__who">
            <p class="site-side__name">hello, traveller</p>
        </div>

        <nav class="site-side__nav" aria-label="Your account">
            <ul class="site-side__links">
                <li><a href="/login"<?= $isCurrent('/login') ? ' aria-current="page"' : '' ?>>log in</a></li>
                <?php if (!empty($registrationOpen)): ?>
                    <li><a href="/register"<?= $isCurrent('/register') ? ' aria-current="page"' : '' ?>>sign up</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</aside>
