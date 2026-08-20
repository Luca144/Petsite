<?php
/**
 * The world bar — every place in Felkyo you can go, under the banner.
 *
 * @package Felkyo\Templates
 *
 * Inserted once by layout.php, inside the main panel's header.
 *
 * WHY IT IS FLAT NOW: navigation used to be nine grouped, labelled pills in the
 * header. The 2026-08-14 redesign splits it — everything that is YOURS lives in
 * the sidebar (partials/site-side.php), and this bar keeps only the places in
 * the world: five pills, which is glanceable without group labels. Still no
 * dropdowns anywhere (CLAUDE.md section 8).
 *
 * Variables, provided by the layout: $currentUser, $currentPath.
 */

/* Pages that do not pass a current path (a plain render in a test, for
   instance) still have to work, so it is defaulted once here. */
$currentPath = $currentPath ?? '';

/* The destinations, kept few enough to read at a glance on a phone (the mockup
   asks for one short row, not the five pills this used to be). Browsing creatures
   and finding people are both "who else is here?", so they now live behind one
   "community" pill as two tabs.

   "myself" is always last and always present. Logged in, it is your own page —
   the same place the sidebar portrait goes, and where the way out now lives.
   Logged out, it is the door: the log-in page, which offers signing up. That is
   the ONLY way in now, since the sidebar no longer greets guests with a login
   box floating over the top of every page. */
$myselfLink = !empty($currentUser)
    ? '/player/' . rawurlencode($currentUser->username)
    : '/login';

if (!empty($currentUser)) {
    $worldLinks = [
        ['/shop', 'shop'],
        ['/explore', 'explore'],
        ['/community', 'community'],
    ];
} else {
    // A guest can look around the world, but the community's people tab and
    // everything personal asks them to come in first.
    $worldLinks = [
        ['/explore', 'explore'],
        ['/community', 'community'],
    ];
}

/* Which link is the current page: itself, or anything beneath it (so
   /explore/meadow still lights up "explore"). Kept as one small function so
   the rule lives in one place instead of being repeated per link. */
$isCurrent = static function (string $href) use ($currentPath): bool {
    return $currentPath === $href || str_starts_with($currentPath, $href);
};
?>
<nav class="site-nav" aria-label="The world">
    <ul class="site-nav__row">
        <?php foreach ($worldLinks as [$href, $label]): ?>
            <li>
                <a href="<?= $this->e($href) ?>"<?= $isCurrent($href) ? ' aria-current="page"' : '' ?>>
                    <?= $this->e($label) ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li>
            <a href="<?= $this->e($myselfLink) ?>"<?= $isCurrent($myselfLink) ? ' aria-current="page"' : '' ?>>
                <?= !empty($currentUser) ? 'myself' : 'log in' ?>
            </a>
        </li>
    </ul>

    <?php if (!empty($currentUser)): ?>
        <?php /* YOUR pages, as a second row — PHONES ONLY (hidden from 900px up,
                 where the sidebar column carries these pills instead; see
                 site-nav.css). The Product Owner asked for the personal buttons
                 to migrate into the task bar on mobile, and his original mockup
                 always drew two rows of pills under the banner. No "home" pill:
                 the banner itself is the way home, as it was on every old-web
                 petsite. */ ?>
        <ul class="site-nav__row site-nav__row--personal">
            <li>
                <a href="/creatures"<?= $isCurrent('/creatures') ? ' aria-current="page"' : '' ?>>my creatures</a>
            </li>
            <li>
                <a href="/inventory"<?= $isCurrent('/inventory') ? ' aria-current="page"' : '' ?>>inventory</a>
            </li>
            <?php if (!empty($showPanelLink)): ?>
                <?php /* Staff only (M2.1): the phone's way into the creator's
                         panel, since this row is what the sidebar's links
                         become at phone width. Convenience, not security —
                         the AdminGate re-checks roles on every request. */ ?>
                <li>
                    <a href="/admin"<?= $isCurrent('/admin') ? ' aria-current="page"' : '' ?>>the panel</a>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
</nav>
