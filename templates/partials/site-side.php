<?php
/**
 * The sidebar — everything that is YOURS, in one parchment panel.
 *
 * @package Felkyo\Templates
 *
 * Inserted once by layout.php, after the main panel (the world greets you first
 * on a phone; the desktop grid places this on the left by name). From 900px up it
 * becomes the tall left column the artist drew (see sidebar.css).
 *
 * WHAT LIVES HERE AND WHY: the 2026-08-14 redesign splits navigation into
 * "you" (this sidebar) and "the world" (the bar under the banner). Your name,
 * your purse, your avatar — which is the door to your own page — the links to
 * your things, the way out, and your favourite creature as a keepsake card.
 *
 * THE WHOLE PANEL FOLDS (added at the Product Owner's request). The summary row —
 * your name and your purse — is always visible; everything beneath it collapses
 * behind a native <details>, so the panel can be put away when you are focusing
 * on other things and is still there when you want it. On a short desktop screen
 * this is also what keeps the sticky column inside the viewport.
 *
 * THE FOLD REMEMBERS ITSELF BETWEEN PAGES. A tiny script (sidebar-fold.js)
 * records each toggle in a cookie, and the SERVER reads that cookie to render the
 * panel already-open or already-closed — so it never springs open only to snap
 * shut again, which was the one failure mode the Product Owner named. Without
 * JavaScript the fold still works; it just starts open on each page, which is a
 * graceful way to degrade.
 *
 * IT DRAWS NOTHING AT ALL FOR A GUEST (changed 2026-08-18): a guest has nothing
 * that is theirs yet, so this panel has nothing to hold; the way in is the
 * "log in" pill in the world bar.
 *
 * Variables, all provided by the layout: $currentUser, $currentPath,
 * $currencyName, $currentAvatarPath, $currentAvatarName, $favouriteSummary,
 * $keepsakeTreats, $sidebarOpen (from the fold cookie).
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
   would still be a parchment box taking up space on every page. */
if (empty($currentUser)) {
    return;
}
?>
<aside class="site-side" aria-label="You">
    <details class="site-side__fold"<?= ($sidebarOpen ?? true) ? ' open' : '' ?>>
        <?php /* The summary is the always-visible line: who you are and what you
                 are carrying. "Can I afford this?" is asked on every page, so the
                 purse never folds away. A <summary> is natively a button to the
                 keyboard and the screen reader — no ARIA gymnastics needed; the
                 chevron is decoration beside that, never the signal itself. */ ?>
        <summary class="site-side__summary">
            <span class="site-side__name"><?= $this->e($currentUser->username) ?></span>
            <span class="site-purse">
                <span aria-hidden="true">&#9670;</span>
                <span class="visually-hidden">You&rsquo;re carrying</span>
                <?= $this->e((string) $currentUser->currencyBalance) ?>
                <?= $this->e($currencyName ?? 'gems') ?>
            </span>
            <span class="site-side__chevron" aria-hidden="true">&#9662;</span>
        </summary>

        <div class="site-side__body">
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

            <?php /* DESKTOP ONLY (see sidebar.css). On a phone these pills live in
                     the world bar under the banner instead — the Product Owner
                     asked for them to migrate to the task bar, and his original
                     mockup always drew them there. */ ?>
            <nav class="site-side__nav" aria-label="Your pages">
                <ul class="site-side__links">
                    <li><a href="/"<?= $isCurrent('/') ? ' aria-current="page"' : '' ?>>home</a></li>
                    <li><a href="/creatures"<?= $isCurrent('/creatures') ? ' aria-current="page"' : '' ?>>my creatures</a></li>
                    <li><a href="/inventory"<?= $isCurrent('/inventory') ? ' aria-current="page"' : '' ?>>inventory</a></li>
                    <?php if (!empty($showPanelLink)): ?>
                        <?php /* Staff only (M2.1). Convenience, not security —
                                 the AdminGate re-checks roles on every admin
                                 request; this link just doesn't clutter a
                                 player's sidebar with a door that isn't theirs. */ ?>
                        <li><a href="/admin"<?= $isCurrent('/admin') ? ' aria-current="page"' : '' ?>>the panel</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <?php if (!empty($favouriteSummary)): ?>
                <?php /* The keepsake: your favourite creature, how it is feeling,
                         and the things you can do about it without leaving the
                         page — on every screen size. */ ?>
                <div class="site-side__favourite">
                    <?= $this->insert('partials/keepsake', [
                        'summary' => $favouriteSummary,
                        'treats' => $keepsakeTreats ?? [],
                    ]) ?>
                </div>
            <?php endif; ?>

            <?php /* The way out — deliberately the quietest control here, last in
                     the column, and DESKTOP ONLY (see sidebar.css). On a phone it
                     lives on your own page, which you reach on purpose. */ ?>
            <form method="post" action="/logout" class="site-side__logout">
                <?= $this->csrf_field() ?>
                <button type="submit">log out</button>
            </form>
        </div>
    </details>
</aside>
