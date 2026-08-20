<?php
/**
 * Everything inside <head>: the title, the fonts, and every stylesheet.
 *
 * @package Felkyo\Templates
 *
 * Inserted once by layout.php. Its own partial because the stylesheet list is the
 * fastest-growing thing in this codebase — M2 alone added six files — and it was
 * pushing layout.php past its 200-line limit while telling you nothing about how
 * the page is arranged. The shell's SHAPE is the interesting part of layout.php;
 * this is the manifest.
 *
 * Variable: $title, provided by whichever page called $this->layout().
 *
 * WHY ONE FILE PER CONCERN AND NO BUNDLER. Splitting the CSS by subject is what
 * keeps each file under the 400-line limit and readable (CLAUDE.md section 3), and
 * the whole set is small enough that loading them separately is simplest — no build
 * step to run, nothing to forget to rebuild, and a stylesheet you can open in a
 * browser's dev tools and recognise. If this list ever gets long enough to hurt,
 * the fix is a build step, not a 2,000-line file.
 */

// The versioned addresses below come from this. See the class for why a changed
// stylesheet needs a changed address.
use Felkyo\Http\AssetUrl;
?>
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
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/forms.css') ?>">
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
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/play.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/keepsake.css') ?>">
    <link rel="stylesheet" href="<?= AssetUrl::versioned('/css/admin.css') ?>">
