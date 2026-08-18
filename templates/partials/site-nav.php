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

/* The destinations. On mobile: just 3 core buttons (shop, explore, myself).
   On desktop: those 3 plus community (which contains browse creatures & find people).
   Guests can only look around — everything else asks them to come in first,
   and the sidebar offers the door (log in / sign up).

   The "myself" link points to the player's profile page when logged in, or
   to the login page when logged out. */
$profileLink = !empty($currentUser) ? '/profile/edit' : '/login';

if (!empty($currentUser)) {
    // Logged-in players: core navigation + community
    $worldLinks = [
        ['/shop', 'shop'],
        ['/explore', 'explore'],
        ['/community', 'community'],
    ];
} else {
    // Guests: can only shop, explore, and log in
    $worldLinks = [
        ['/shop', 'shop'],
        ['/explore', 'explore'],
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
            <a href="<?= $this->e($profileLink) ?>"<?= $isCurrent($profileLink) ? ' aria-current="page"' : '' ?>>
                myself
            </a>
        </li>
    </ul>
</nav>
