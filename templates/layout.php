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
<?php /* M4.3: easy-reading type is switched on for the whole page by one
         attribute, which themes.css answers. */ ?>
<html lang="en"<?= !empty($activeTheme) && $activeTheme->easyReading ? ' data-reading="easy"' : '' ?>>
<head>
    <?= $this->insert('partials/head', ['title' => $title]) ?>
</head>
<body>
    <!-- Keyboard users can jump straight to the content with this link. -->
    <a class="skip-link" href="#main">Skip to content</a>

    <!-- Decorative drifting stars (four-pointed ✦ and ✧ — Skerro asked for stars,
         not the old floral glyphs, which read as leaves); hidden from screen readers and from anyone
         who prefers reduced motion. They now fall in front of a real sky. -->
    <?php /* A theme may choose other drifting symbols, or none (ThemeStyle::MOTES).
             They are HTML entities from that list, never typed text, so they
             are printed as they are. */
    $motes = !empty($activeTheme) ? $activeTheme->style->motes() : ['&#10022;', '&#10023;']; ?>
    <?php if ($motes !== []): ?>
        <div class="site-motes" aria-hidden="true">
            <span><?= $motes[0] ?></span><span><?= $motes[1] ?></span><span><?= $motes[0] ?></span><span><?= $motes[1] ?></span>
        </div>
    <?php endif; ?>

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
            <?php /* The server clock — the classic petsite "server time" touch.
                     The server's own date(), nothing from the visitor, purely
                     decorative (the page works identically without reading it). */ ?>
            <p class="site-clock"><?= $this->e(date('H:i, M jS')) ?></p>

            <header class="site-header">
                <?php if (!empty($creatureMoment)): ?>
                    <?php /* A creature chose this page to pop up on (a rare roll —
                             see CreatureMoments). It lives INSIDE the header so
                             that from 900px up the popup sits fully within the
                             banner art — anchored to the header itself, it cannot
                             drift onto the panel edge whatever the paddings around
                             it do ("this still needs pushing inside the banner",
                             and he was right). On a phone it simply flows here,
                             right at the top under the clock. */ ?>
                    <?= $this->insert('partials/creature-moment', ['moment' => $creatureMoment]) ?>
                <?php endif; ?>
                <!-- The masthead is the artist's wide painted banner on every
                     screen size. At phone width it scales down to a ~67px strip —
                     a warm little masthead rather than a full-screen landing
                     (the old square badge filled half the screen on every page).
                     The alt text carries the site's name for screen readers and
                     for anyone whose images fail to load. -->
                <h1 class="site-header__wordmark">
                    <?php /* The banner is the way home — the oldest petsite
                             convention there is, and the reason the world bar
                             needs no "home" pill. The link's accessible name is
                             the image's alt, so a screen reader hears "Felkyo
                             Creatures" once, as a link. */ ?>
                    <a class="site-header__home" href="/">
                        <?php /* A theme may bring its own banner (uploaded as
                                 site art); otherwise the painted logo. */ ?>
                        <img class="site-header__banner" src="<?= $this->e(!empty($activeTheme) && $activeTheme->bannerUrl() !== null ? $activeTheme->bannerUrl() : '/assets/art/logo-large.png') ?>"
                             alt="Felkyo Creatures" width="800" height="150">
                    </a>
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
                <?php if (!empty($isPreviewingCss)): ?>
                    <?php /* Only the person previewing fine-tuning CSS sees this —
                             a reminder on every page, so a draft is never
                             forgotten (and never mistaken for what everyone sees). */ ?>
                    <p class="look-preview-bar" role="status">
                        You&rsquo;re previewing fine-tuning CSS &mdash; only you see it.
                        <a href="/admin/look/css">Publish or stop</a>
                    </p>
                <?php endif; ?>
                <?php if (!empty($announcement)): ?>
                    <?php /* The panel's announcement line (Site look). Staff-written,
                             and escaped like everything else. */ ?>
                    <p class="site-announcement" role="note">
                        <span aria-hidden="true">&#10022;</span> <?= $this->e($announcement) ?>
                    </p>
                <?php endif; ?>
                <?php foreach ($unreadNotices ?? [] as $notice): ?>
                    <?php /* Official notices (M2.7) — the one-way channel. They
                             sit ABOVE the flash and on every page until the
                             player presses "I've read this": a notice is the
                             site formally telling somebody something, and a
                             card that can be scrolled past unseen would be the
                             silence-or-suspension gap all over again. */ ?>
                    <?= $this->insert('partials/notice-card', [
                        'notice' => $notice,
                        'appealEmail' => $appealEmail ?? '',
                    ]) ?>
                <?php endforeach; ?>
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
                <?php if (!empty($currentUser)): ?>
                    <?php /* Bug reports, from every page: the link carries this
                             page's address so the player only has to say what
                             happened. Logged-in players only (see BugReportController). */ ?>
                    <a class="site-footer__bug" href="/bug-report?from=<?= $this->e(rawurlencode($currentPath ?? '/')) ?>">
                        <span aria-hidden="true">&#128027;&nbsp;</span>Something broken? Tell us
                    </a>
                <?php endif; ?>
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
            // Whether the panel was left folded away (from the fold cookie).
            'sidebarOpen' => $sidebarOpen ?? true,
        ]) ?>
    </div>

    <?php /* Site-wide enhancements. Everything here is an ENHANCEMENT: each page
             works fully without it. Page-specific scripts (item-finder.js) are
             included by the pages that need them, NOT here — loading one both
             here and there ran it twice and bound every handler twice over. */ ?>
    <script src="<?= AssetUrl::versioned('/js/password-toggle.js') ?>" defer></script>
    <script src="<?= AssetUrl::versioned('/js/sidebar-fold.js') ?>" defer></script>
</body>
</html>
