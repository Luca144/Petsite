<?php
/**
 * Base page layout — the themed shell every page shares.
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: the outer HTML wrapper for the whole site. Since the 2026-08-14
 * redesign (docs/plan/2026-08-14-the-new-face.md) the shell is TWO parchment
 * panels floating on the artist's painted starfield:
 *
 *   - the main panel holding THE WORLD — the painted banner, the row of
 *     world destinations (partials/site-nav.php), the page content, the footer;
 *   - a sidebar (partials/site-side.php) holding everything that is YOURS —
 *     name, purse, avatar, personal links, log out, favourite creature.
 *
 * On a phone the two panels simply stack in document order: THE WORLD FIRST,
 * then your own panel. It used to be the other way round, and every page opened
 * with your own name and keepsake card while the banner arrived a screen later —
 * a site should greet you with the world, not with yourself. From 900px up the
 * grid places the sidebar on the left BY NAME, so the wide layout is unaffected
 * by the markup order (see layout.css).
 *
 * HOW IT FITS TOGETHER: a page template calls $this->layout('layout', [...]) and
 * defines a "content" section; this file wraps that content in the shared shell.
 *
 * Plates escapes values passed in via $this->e(...), so any user-provided text
 * that reaches the layout cannot break the page or inject scripts.
 *
 * The whole <head> — title, fonts, every stylesheet — lives in partials/head.php.
 * The stylesheet list is the fastest-growing thing in this codebase and told you
 * nothing about how the page is arranged; the shell's SHAPE is what this file is
 * for.
 */

// For the versioned addresses of the scripts at the very bottom. See the class for
// why a changed file needs a changed address.
use Felkyo\Http\AssetUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->insert('partials/head', ['title' => $title]) ?>
</head>
<body>
    <!-- Keyboard users can jump straight to the content with this link. -->
    <a class="skip-link" href="#main">Skip to content</a>

    <!-- Decorative drifting sparkles; hidden from screen readers and from anyone
         who prefers reduced motion. They now fall in front of a real sky. -->
    <div class="site-motes" aria-hidden="true">
        <span>&#10086;</span><span>&#10022;</span><span>&#10087;</span><span>&#10022;</span>
    </div>

    <?php if (!empty($showDemoNotice)): ?>
        <!-- On the deployed site this says plainly that Felkyo is a demo, not a
             live service, so nobody mistakes it for one. role="note" marks it as
             an aside rather than an alert — it is information, not a warning, and
             should not interrupt anyone. -->
        <p class="demo-notice" role="note">
            <span aria-hidden="true">&#9788;</span>
            This is a <b>development demo</b> of Felkyo Creatures &mdash; not a live
            service. Accounts and creatures here are made up for demonstration.
        </p>
    <?php endif; ?>

    <?php /* id="top" is the target of the footer's "Back to top" link.

             THE MODIFIER CLASS SAYS WHETHER THERE IS A SIDEBAR, and it has to,
             because the wide layout is a two-column grid. A guest gets no sidebar
             panel at all (see partials/site-side.php for why), which left the grid
             with one child — and a single child lands in the FIRST column, which
             is sidebar-width. The whole site rendered squeezed into a 250px strip
             with the painted sky filling the rest.

             A modifier rather than :has(> .site-side): it is obvious when reading
             either file which case is which, and it cannot be defeated by
             something else appearing inside the shell later. */ ?>
    <div class="site-shell<?= !empty($currentUser) ? ' site-shell--with-side' : '' ?>" id="top">
        <?php /* THE MAIN PANEL COMES FIRST IN THE MARKUP, the sidebar after it.

                 On a phone the panels simply stack in document order, and what
                 stacked first used to be the sidebar — so every page opened with
                 your own name, your pills and your keepsake card, and the banner
                 (the thing that says where you ARE) arrived a full screen later.
                 The artist's mockup opens with the banner, and he was right to be
                 cross: a site should greet you with the world, not with yourself.

                 The wide layout is unaffected: the grid places each panel by name
                 (see layout.css), so the sidebar still sits on the left there.
                 Screen readers get the better end of this too — the actual content
                 now comes before the chrome that is the same on every page. */ ?>
        <div class="site-main">
            <?php if (!empty($creatureMoment)): ?>
                <?php /* A creature chose this page to pop up on (a rare roll — see
                         CreatureMoments).

                         IT IS FIRST IN THE PANEL, above the clock and the banner,
                         which is where the artist's mockup puts it. It used to sit
                         inside <main>, below the whole header — so on a phone the
                         creature said hello a scroll after you had arrived, which
                         is not a greeting.

                         From 900px up the stylesheet lifts it out of the flow and
                         over the banner as the popup the mockup draws, so its
                         position here only decides the phone layout and the order
                         a screen reader reads it in. Both now match what a sighted
                         phone user sees, which is the point. */ ?>
                <?= $this->insert('partials/creature-moment', ['moment' => $creatureMoment]) ?>
            <?php endif; ?>

            <?php /* The server clock — the classic petsite "server time" touch.
                     The server's own date(), nothing from the visitor, purely
                     decorative (the page works identically without reading it). */ ?>
            <p class="site-clock"><?= $this->e(date('H:i, M jS')) ?></p>

            <header class="site-header">
                <!-- The masthead is the artist's wide painted banner on every
                     screen size. At phone width it scales down to a ~67px strip —
                     a warm little masthead rather than a full-screen landing
                     (the old square badge filled half the screen on every page).
                     The alt text carries the site's name for screen readers and
                     for anyone whose images fail to load. -->
                <h1 class="site-header__wordmark">
                    <img class="site-header__banner" src="/assets/art/logo-large.png"
                         alt="Felkyo Creatures" width="800" height="150">
                </h1>

                <?php if (empty($currentUser)): ?>
                    <?php /* The welcome is for somebody who has not arrived yet. Once
                             you are in, this space stays quiet — the creatures speak
                             for themselves now and then (see CreatureMoments). */ ?>
                    <p class="site-header__tagline">come in &mdash; the kettle&rsquo;s on</p>
                <?php endif; ?>

                <?= $this->insert('partials/site-nav', [
                    'currentUser' => $currentUser ?? null,
                    'currentPath' => $currentPath ?? '',
                ]) ?>
            </header>

            <hr class="rule">

            <!-- The page's own content is dropped in here. -->
            <main id="main">
                <?php if (!empty($flash)): ?>
                    <?php /* The one-time message from whatever just happened, shown
                             for EVERY page rather than by each page separately.

                             It used to be per-page, and the home page had no such
                             block — so feeding a creature from the keepsake card,
                             which lands you back on the home page, silently said
                             nothing. golden rule 4 is that every action gets a
                             visible answer, and the only way to keep that promise
                             is for the answer not to be optional.

                             role="status" announces it politely to a screen reader
                             rather than interrupting. */ ?>
                    <p class="flash" role="status"><?= $this->e($flash) ?></p>
                <?php endif; ?>

                <?= $this->section('content') ?>
            </main>

            <footer class="site-footer">
                <?php /* "Back to top" — honest and old-web. Scrolls to the shell's
                         id="top"; smooth only for people who allow motion. */ ?>
                <a class="site-footer__top" href="#top">
                    <span aria-hidden="true">&#9650;</span> Back to top
                </a>
                <span class="site-footer__line">
                    &copy; Felkyo Creatures <?= $this->e(date('Y')) ?> &middot; made with
                    <span class="heart" aria-hidden="true">&hearts;</span> &middot;
                    a warm little corner of the web
                </span>
            </footer>
        </div>

        <?= $this->insert('partials/site-side', [
            'currentUser' => $currentUser ?? null,
            'currentPath' => $currentPath ?? '',
            'currencyName' => $currencyName ?? 'gems',
            'registrationOpen' => $registrationOpen ?? false,
            'currentAvatarPath' => $currentAvatarPath ?? null,
            'currentAvatarName' => $currentAvatarName ?? null,
            'favouriteSummary' => $favouriteSummary ?? null,
            // The treats the player is carrying, so the keepsake card can offer
            // them. Empty for a guest, and empty for anybody with none — the card
            // says where to get some rather than hiding the button.
            'keepsakeTreats' => $keepsakeTreats ?? [],
        ]) ?>
    </div>

    <?php /* Site-wide enhancements. Everything here is an ENHANCEMENT: each page
             works fully without it. Page-specific scripts (item-finder.js) are
             included by the pages that need them, NOT here — loading one both
             here and there ran it twice and bound every handler twice over. */ ?>
    <script src="<?= AssetUrl::versioned('/js/password-toggle.js') ?>" defer></script>
</body>
</html>
