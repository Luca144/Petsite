<?php
/**
 * Base page layout — the themed shell every page shares.
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: the outer HTML wrapper for the whole site — the gold-edged
 * parchment frame, the masthead (logo + tagline), the navigation, and the
 * footer. Individual page templates fill the "content" section in the middle.
 *
 * HOW IT FITS TOGETHER: a page template calls $this->layout('layout', [...]) and
 * defines a "content" section; this file wraps that content in the shared shell.
 * The look comes entirely from the three stylesheets in /public/css.
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

    <!-- Our styles, split by concern: tokens/base, layout, components, and the
         creature page. Small enough that loading them together is simplest. -->
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/theme.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/layout.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/components.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/creature.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/explore.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/economy.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/item-detail.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/profile.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/social.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/guestbook.css') ?>">
</head>
<body>
    <!-- Keyboard users can jump straight to the content with this link. -->
    <a class="skip-link" href="#main">Skip to content</a>

    <!-- Decorative drifting sparkles; hidden from screen readers and from anyone
         who prefers reduced motion. -->
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

    <div class="site-frame">
        <div class="site-frame__inner">
            <header class="site-header">
                <!--
                    The wordmark is the artist's logo art. Two versions were drawn: a
                    square badge that suits a phone, and a wide banner that suits a
                    desktop. <picture> lets the BROWSER choose between them with no
                    JavaScript at all — it reads the media rule and downloads only the
                    file it is actually going to show.

                    Mobile-first (CLAUDE.md section 8): the plain <img> is the phone
                    version, and the wide banner is opted into from 640px upward. The
                    alt text is the site's name, so screen-reader users and anyone
                    whose images fail to load still get the branding.
                -->
                <h1 class="site-header__wordmark">
                    <picture>
                        <source media="(min-width: 640px)" srcset="/assets/art/logo-large.png"
                                width="800" height="150">
                        <img class="site-header__logo" src="/assets/art/logo-small.png"
                             alt="Felkyo Creatures" width="256" height="256">
                    </picture>
                </h1>
                <!-- The square badge only reads "Felkyo", so this line completes the
                     name on a phone. It is hidden from screen readers because the logo's
                     alt text already says "Felkyo Creatures" — without aria-hidden they
                     would hear "Creatures" twice. On wide screens the banner art already
                     contains the word, so the stylesheet hides this line there. -->
                <div class="site-header__sub" aria-hidden="true">Creatures</div>
                <p class="site-header__tagline">come in &mdash; the kettle&rsquo;s on</p>

                <nav class="site-nav" aria-label="Main">
                    <!-- aria-current marks the page you are on for screen readers.
                         $currentPath is provided by the front controller. -->
                    <a href="/"<?= ($currentPath ?? '') === '/' ? ' aria-current="page"' : '' ?>>home</a>
                    <!-- Browse is public, so it is shown to everyone. -->
                    <a href="/browse"<?= ($currentPath ?? '') === '/browse' ? ' aria-current="page"' : '' ?>>browse</a>

                    <?php if (!empty($currentUser)): ?>
                        <!-- Logged in: links to the player's own pages, then greet
                             them and offer log out. Logging out changes state, so it
                             is a POST form with a CSRF token, not a plain link. -->
                        <a href="/creatures"<?= ($currentPath ?? '') === '/creatures' ? ' aria-current="page"' : '' ?>>my creatures</a>
                        <a href="/adopt"<?= ($currentPath ?? '') === '/adopt' ? ' aria-current="page"' : '' ?>>adopt</a>
                        <a href="/explore"<?= str_starts_with($currentPath ?? '', '/explore') ? ' aria-current="page"' : '' ?>>explore</a>
                        <a href="/shop"<?= str_starts_with($currentPath ?? '', '/shop') ? ' aria-current="page"' : '' ?>>shop</a>
                        <a href="/inventory"<?= ($currentPath ?? '') === '/inventory' ? ' aria-current="page"' : '' ?>>things</a>
                        <a href="/players"<?= ($currentPath ?? '') === '/players' ? ' aria-current="page"' : '' ?>>find people</a>
                        <a href="/player/<?= $this->e(rawurlencode($currentUser->username)) ?>"<?= str_starts_with($currentPath ?? '', '/player/') || str_starts_with($currentPath ?? '', '/profile') ? ' aria-current="page"' : '' ?>>my page</a>
                        <span class="site-nav__user">hi, <?= $this->e($currentUser->username) ?></span>
                        <span class="site-nav__coins">
                            <span aria-hidden="true">&#9670;</span>
                            <?= $this->e((string) $currentUser->currencyBalance) ?> <?= $this->e($currencyName ?? 'coins') ?>
                        </span>
                        <form method="post" action="/logout" class="site-nav__form">
                            <?= $this->csrf_field() ?>
                            <button type="submit">log out</button>
                        </form>
                    <?php else: ?>
                        <!-- Logged out: offer the ways in. The sign-up link only
                             appears when registration is actually open — on the
                             deployed demo it is closed, and offering a link that
                             leads to "no thanks" would be unkind. The controller
                             refuses the action too; hiding a link is never the
                             protection, only the courtesy. -->
                        <a href="/login"<?= ($currentPath ?? '') === '/login' ? ' aria-current="page"' : '' ?>>log in</a>
                        <?php if (!empty($registrationOpen)): ?>
                            <a href="/register"<?= ($currentPath ?? '') === '/register' ? ' aria-current="page"' : '' ?>>sign up</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </nav>
            </header>

            <hr class="rule">

            <!-- The page's own content is dropped in here. -->
            <main id="main">
                <?= $this->section('content') ?>
            </main>

            <footer class="site-footer">
                Felkyo Creatures &middot; made with
                <span class="heart" aria-hidden="true">&hearts;</span> &middot;
                a warm little corner of the web
            </footer>
        </div>
    </div>
</body>
</html>
