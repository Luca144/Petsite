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
 * IT DRAWS NOTHING AT ALL FOR A GUEST (changed 2026-08-18). It used to greet a
 * logged-out visitor with "hello, traveller" and a log-in link, which on a phone
 * meant a whole parchment panel sitting above the banner before anyone had seen
 * what the site even was — the first thing you met was a login box floating over
 * the page. A guest has nothing that is theirs yet, so this panel has nothing to
 * hold; the way in is the "myself" pill in the world bar, which leads to the
 * log-in page, and the log-in page offers signing up.
 *
 * Variables, all provided by the layout: $currentUser, $currentPath,
 * $currencyName, $currentAvatarPath, $currentAvatarName, $favouriteSummary (a
 * creature summary array, or null when no favourite is chosen — then the
 * keepsake slot simply stays empty).
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

/* A guest gets no panel at all — not an empty one. An <aside> with nothing in it
   would still be a parchment box taking up the top of every page. */
if (empty($currentUser)) {
    return;
}
?>
<aside class="site-side" aria-label="You">
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
                <img class="site-side__portrait-img"
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

        <?php /* The way out — deliberately the quietest control here, and DESKTOP
                 ONLY (see sidebar.css). On a phone the sidebar is a compact strip
                 right at the top of every page, which put "log out" a mis-tap away
                 from the links people actually use all day. It lives on your own
                 page there instead, which is where you go on purpose. */ ?>
        <form method="post" action="/logout" class="site-side__logout">
            <?= $this->csrf_field() ?>
            <button type="submit">log out</button>
        </form>

        <?php if (!empty($favouriteSummary)): ?>
            <?php /* The keepsake: your favourite creature, how it is feeling, and
                     two things you can do about it without leaving the page.
                     It used to be the plain collection card — a picture and a
                     link, which is a shortcut rather than a companion. Now it is
                     the creature itself, on every screen.

                     SHOWN ON PHONES TOO (changed in M2). The card was
                     desktop-only because it cost scroll height and only saved a
                     tap. Now it is the feature, and a feature you can only reach
                     on a laptop is not one most players have. */ ?>
            <div class="site-side__favourite">
                <?= $this->insert('partials/keepsake', [
                    'summary' => $favouriteSummary,
                    'treats' => $keepsakeTreats ?? [],
                ]) ?>
            </div>
        <?php endif; ?>
</aside>
