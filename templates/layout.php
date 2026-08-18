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
 *   - a sidebar (partials/site-side.php) holding everything that is YOURS —
 *     name, purse, avatar, personal links, log out, favourite creature;
 *   - the main panel holding THE WORLD — the painted banner, the row of
 *     world destinations (partials/site-nav.php), the page content, the footer.
 *
 * On a phone the two panels simply stack, sidebar first as a compact strip.
 * The side-by-side arrangement appears from 900px up (see layout.css).
 *
 * HOW IT FITS TOGETHER: a page template calls $this->layout('layout', [...]) and
 * defines a "content" section; this file wraps that content in the shared shell.
 *
 * Plates escapes values passed in via $this->e(...), so any user-provided text
 * that reaches the layout cannot break the page or inject scripts.
 */

// The versioned stylesheet addresses below come from this. See the class for
// why a changed stylesheet needs a changed address.
use Felkyo\Http\AssetUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <!-- Mobile-first: use the phone's real width. The site is designed for a
         ~360px screen first, then refined upward. -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?></title>

    <!-- The browser-tab icon, from the artist's logo set. -->
    <link rel="icon" href="/assets/art/favicon.png" type="image/png">

    <!-- Web fonts (Fraunces / Nunito / Space Mono). Preconnect speeds up the
         download; every font stack in theme.css has fallbacks if these fail. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,900&family=Nunito:wght@400;600;700;800&family=Space+Mono:wght@400;700&display=swap">

    <!-- Our styles, split by concern: tokens/base, layout, sidebar, nav, and the
         per-page stylesheets. Small enough that loading them together is simplest. -->
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/theme.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/sidebar.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/site-nav.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/components.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/creature.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/creature-actions.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/explore.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/economy.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/creature-shop.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/item-detail.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/profile.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/social.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/guestbook.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/community.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/mood.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/keepsake.css') ?>">
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

    <!-- id="top" is the target of the footer's "Back to top" link. -->
    <div class="site-shell" id="top">
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

        <div class="site-main">
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
                <?php if (!empty($creatureMoment)): ?>
                    <?php /* A creature chose this page to pop up on (a rare roll —
                             see CreatureMoments). On a phone it sits here, above the
                             content; from 900px up the stylesheet lifts it over the
                             banner as the little popup the artist drew. */ ?>
                    <?= $this->insert('partials/creature-moment', ['moment' => $creatureMoment]) ?>
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
    </div>

    <?php /* Site-wide enhancements. Everything here is an ENHANCEMENT: each page
             works fully without it. Page-specific scripts (item-finder.js) are
             included by the pages that need them, NOT here — loading one both
             here and there ran it twice and bound every handler twice over. */ ?>
    <script src="<?= AssetUrl::versioned('/js/password-toggle.js') ?>" defer></script>
</body>
</html>
