<?php
/**
 * Base page layout — the themed shell every page shares.
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: the outer HTML wrapper for the whole site — the gold-edged
 * parchment frame, the masthead (wordmark + tagline), the navigation, and the
 * footer. Individual page templates fill the "content" section in the middle.
 *
 * HOW IT FITS TOGETHER: a page template calls $this->layout('layout', [...]) and
 * defines a "content" section; this file wraps that content in the shared shell.
 * The look comes entirely from the three stylesheets in /public/css.
 *
 * NOTE ON THE LOGO: the wordmark below is a typographic placeholder set in
 * Fraunces. The finished logo art will replace it during the art-import step.
 *
 * Plates escapes values passed in via $this->e(...), so any user-provided text
 * that reaches the layout cannot break the page or inject scripts.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <!-- Mobile-first: use the phone's real width. The site is designed for a
         ~360px screen first, then refined upward. -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?></title>

    <!-- A tiny inline moon favicon so there is no broken-icon request. The real
         favicon arrives with the logo art. -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%233D2B4F'/><text y='24' x='6' font-size='20'>%E2%98%BE</text></svg>">

    <!-- Web fonts (Fraunces / Nunito / Space Mono). Preconnect speeds up the
         download; every font stack in theme.css has fallbacks if these fail. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,900&family=Nunito:wght@400;600;700;800&family=Space+Mono:wght@400;700&display=swap">

    <!-- Our styles, split by concern: tokens/base, layout, components. -->
    <link rel="stylesheet" href="/css/theme.css">
    <link rel="stylesheet" href="/css/layout.css">
    <link rel="stylesheet" href="/css/components.css">
</head>
<body>
    <!-- Keyboard users can jump straight to the content with this link. -->
    <a class="skip-link" href="#main">Skip to content</a>

    <!-- Decorative drifting sparkles; hidden from screen readers and from anyone
         who prefers reduced motion. -->
    <div class="site-motes" aria-hidden="true">
        <span>&#10086;</span><span>&#10022;</span><span>&#10087;</span><span>&#10022;</span>
    </div>

    <div class="site-frame">
        <div class="site-frame__inner">
            <header class="site-header">
                <span class="site-header__moon" aria-hidden="true">&#9790;</span>
                <!-- The wordmark. The gold "kyo" is decoration (gold-on-parchment
                     is only used decoratively, never for readable text). -->
                <h1 class="site-header__wordmark">Fel<b>kyo</b></h1>
                <div class="site-header__sub">Creatures</div>
                <p class="site-header__tagline">come in &mdash; the kettle&rsquo;s on</p>

                <nav class="site-nav" aria-label="Main">
                    <!-- aria-current marks the page you are on for screen readers.
                         $currentPath is provided by the front controller. -->
                    <a href="/"<?= ($currentPath ?? '') === '/' ? ' aria-current="page"' : '' ?>>home</a>

                    <?php if (!empty($currentUser)): ?>
                        <!-- Logged in: greet the user and offer log out. Logging out
                             changes state, so it is a POST form with a CSRF token,
                             not a plain link. -->
                        <span class="site-nav__user">hi, <?= $this->e($currentUser->username) ?></span>
                        <form method="post" action="/logout" class="site-nav__form">
                            <?= $this->csrf_field() ?>
                            <button type="submit">log out</button>
                        </form>
                    <?php else: ?>
                        <!-- Logged out: offer the two ways in. -->
                        <a href="/login"<?= ($currentPath ?? '') === '/login' ? ' aria-current="page"' : '' ?>>log in</a>
                        <a href="/register"<?= ($currentPath ?? '') === '/register' ? ' aria-current="page"' : '' ?>>sign up</a>
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
