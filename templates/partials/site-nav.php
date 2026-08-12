<?php
/**
 * The site navigation, and the small strip saying who you are.
 *
 * @package Felkyo\Templates
 *
 * Inserted once by layout.php. It lives in its own file because the layout was
 * growing past the 200-line limit in CLAUDE.md section 3, and because the
 * navigation is a self-contained thing that can now be read without scrolling
 * past the page frame around it.
 *
 * Variables it uses, all provided by the layout: $currentUser, $currentPath,
 * $currencyName, $registrationOpen.
 */
?>
<?php
/*
 * THE NAVIGATION IS GROUPED ON PURPOSE.
 *
 * It used to be one flat row of eleven identical pills — every
 * destination, plus the player's name, coins and log-out button, all
 * looking exactly alike. It read as a heap of buttons rather than a
 * way of getting somewhere, and nothing told you which of them were
 * even the same KIND of thing.
 *
 * Two changes fix that, and neither needs a dropdown (CLAUDE.md
 * section 8 forbids them, rightly):
 *
 * 1. WHO YOU ARE is not navigation. Your name, your purse and log out
 *    have moved out of the nav into their own quiet strip below it.
 *    They are status, not destinations.
 *
 * 2. WHERE YOU CAN GO is split into three small groups with a space
 *    between them: what's yours, where you can wander, and other
 *    people. Three groups of three is something you can take in at a
 *    glance; nine in a row is a list you have to read.
 *
 * The groups are marked up as real lists so a screen reader announces
 * "list of 3 items" and its label, which is the same help the spacing
 * gives everyone else.
 */
/* Pages that do not pass a current path (a plain render in a test, for
   instance) still have to work, so it is defaulted once here rather
   than guarded at every use. */
$currentPath = $currentPath ?? '';

$navGroups = [];

if (!empty($currentUser)) {
    $navGroups = [
        'Yours' => [
            ['/creatures', 'my creatures'],
            ['/inventory', 'my things'],
            ['/player/' . rawurlencode($currentUser->username), 'my page'],
        ],
        'Wander' => [
            ['/adopt', 'adopt'],
            ['/explore', 'explore'],
            ['/shop', 'shop'],
        ],
        'Everyone' => [
            ['/browse', 'browse creatures'],
            ['/players', 'find people'],
        ],
    ];
} else {
    $navGroups = [
        'Look around' => [['/browse', 'browse creatures']],
    ];
}

/* Which link is the current page. Kept as one small function so the
   rule lives in one place instead of being repeated per link. */
$isCurrent = static function (string $href) use ($currentPath): bool {
    if ($href === '/player/' . rawurlencode($currentUser->username ?? '')) {
        return str_starts_with($currentPath ?? '', '/player/')
            || str_starts_with($currentPath ?? '', '/profile');
    }

    return $href === ($currentPath ?? '')
        || ($href !== '/' && str_starts_with($currentPath ?? '', $href));
};
?>

<nav class="site-nav" aria-label="Main">
    <a class="site-nav__home"
       href="/"<?= ($currentPath ?? '') === '/' ? ' aria-current="page"' : '' ?>>home</a>

    <?php foreach ($navGroups as $groupName => $links): ?>
        <?php /* The group name is SHOWN, not only announced to screen readers.
                 Spacing alone was not enough: the groups wrap on a narrow screen
                 and the gaps carrying the meaning wrap away with them, leaving a
                 row of buttons in no obvious order. A word above each group
                 survives any amount of wrapping, and answers the fair question
                 "why are they sorted like this?" without anything to click. */ ?>
        <div class="site-nav__set">
            <p class="site-nav__set-label" id="nav-<?= $this->e(strtolower($groupName)) ?>">
                <?= $this->e($groupName) ?>
            </p>
            <ul class="site-nav__group" aria-labelledby="nav-<?= $this->e(strtolower($groupName)) ?>">
                <?php foreach ($links as [$href, $label]): ?>
                    <li>
                        <a href="<?= $this->e($href) ?>"<?= $isCurrent($href) ? ' aria-current="page"' : '' ?>>
                            <?= $this->e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <?php if (empty($currentUser)): ?>
        <ul class="site-nav__group" aria-label="Your account">
            <li>
                <a href="/login"<?= ($currentPath ?? '') === '/login' ? ' aria-current="page"' : '' ?>>log in</a>
            </li>
            <?php if (!empty($registrationOpen)): ?>
                <li>
                    <a href="/register"<?= ($currentPath ?? '') === '/register' ? ' aria-current="page"' : '' ?>>sign up</a>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
</nav>
