<?php
/**
 * The finder row — category pills and a name search, shared by the economy pages.
 *
 * @package Felkyo\Templates
 *
 * Insert it with $this->insert('partials/item-finder', ['finder' => [...]]).
 * $finder carries everything as one array (max-4-parameters rule):
 *   'action'        => string — the page's own URL, e.g. '/inventory'
 *   'categories'    => list of ['slug','name','iconKey','count']  (present in the full list)
 *   'activeSlug'    => string — '' means "everything"
 *   'searchText'    => string — the cleaned search, echoed back into the box
 *   'totalCount'    => int — how many things exist before filtering
 *   'shownCount'    => int — how many survived the filters
 *   'searchShownFrom' => int — show the search box from this many things up
 *   'thingsWord'    => string — 'things' on the inventory, 'items' in the shop
 *   'emptyLine'     => string — what to say when a filter matches nothing
 *
 * DESIGN DECISIONS (from docs/plan/2026-08-13-usability-pass.md):
 * - The pills are LINKS, never a dropdown (CLAUDE.md section 8). Each carries
 *   its icon, its name in words, and a count — three signals, like the cards.
 * - Pills keep the current search in their URLs and the form keeps the current
 *   category in a hidden field, so the two filters combine instead of
 *   resetting each other.
 * - The search box only appears when the list is big enough to need it, or
 *   when a search is already running (so the state is always editable).
 * - An active filter always says what happened: "Showing 3 of 12".
 *
 * WORKS WITHOUT JAVASCRIPT, BETTER WITH IT: everything here is real links and
 * a real GET form. public/js/item-finder.js additionally filters in place —
 * no reload — but ONLY when data-complete-list below says the whole list is
 * in the page. The data-* attributes are that script's contract; renaming one
 * here means renaming it there.
 */
$categories = $finder['categories'];
$activeSlug = $finder['activeSlug'];
$searchText = $finder['searchText'];
$isFiltered = $activeSlug !== '' || $searchText !== '';
$showSearch = $finder['totalCount'] >= $finder['searchShownFrom'] || $searchText !== '';

// The pills only earn their row when there is more than one category to
// choose between; a single pill plus "everything" is a choice of nothing.
$showPills = count($categories) > 1;
?>
<?php if ($showPills || $showSearch): ?>
    <div class="finder"
         data-action="<?= $this->e($finder['action']) ?>"
         data-things-word="<?= $this->e($finder['thingsWord']) ?>"
         <?= $isFiltered ? '' : 'data-complete-list' ?>>
        <?php if ($showSearch): ?>
            <form class="finder__search" method="get" action="<?= $this->e($finder['action']) ?>">
                <?php /* The active category survives a new search via this hidden
                         field — GET forms drop everything not inside them. */ ?>
                <?php if ($activeSlug !== ''): ?>
                    <input type="hidden" name="category" value="<?= $this->e($activeSlug) ?>">
                <?php endif; ?>
                <label class="finder__label" for="finder-q">Find by name</label>
                <div class="finder__row">
                    <input class="finder__input" type="search" id="finder-q" name="q"
                           value="<?= $this->e($searchText) ?>"
                           maxlength="40" autocomplete="off">
                    <button class="btn btn--secondary finder__button" type="submit">Find</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($showPills): ?>
            <ul class="finder__pills" aria-label="Filter by category">
                <?php
                // Every pill link keeps the current search text, so picking a
                // category narrows the search instead of throwing it away.
                $searchPart = $searchText !== '' ? '?q=' . rawurlencode($searchText) : '';
                ?>
                <li>
                    <a class="finder__pill" data-category=""
                       href="<?= $this->e($finder['action'] . ($searchPart !== '' ? $searchPart : '')) ?>"
                       <?= $activeSlug === '' ? 'aria-current="true"' : '' ?>>
                        everything &middot; <?= $this->e((string) $finder['totalCount']) ?>
                    </a>
                </li>
                <?php foreach ($categories as $category): ?>
                    <?php
                    $href = $finder['action'] . '?category=' . rawurlencode($category['slug'])
                        . ($searchText !== '' ? '&q=' . rawurlencode($searchText) : '');
                    ?>
                    <li>
                        <a class="finder__pill" data-category="<?= $this->e($category['slug']) ?>"
                           href="<?= $this->e($href) ?>"
                           <?= $activeSlug === $category['slug'] ? 'aria-current="true"' : '' ?>>
                            <svg class="category-icon" aria-hidden="true" focusable="false">
                                <use href="#icon-<?= $this->e($category['iconKey']) ?>"></use>
                            </svg>
                            <?= $this->e($category['name']) ?> &middot; <?= $this->e((string) $category['count']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php /* Say what happened (golden rule 4). Rendered even when nothing is
                 filtered yet — hidden — so the in-browser filtering has a line
                 to fill in. role="status" makes each new count heard, not only
                 seen. The text lives in its own span so the script can update
                 the words without touching the link beside them. */ ?>
        <p class="finder__count" role="status" <?= $isFiltered ? '' : 'hidden' ?>>
            <span class="finder__count-text"><?= $this->e('Showing ' . $finder['shownCount'] . ' of ' . $finder['totalCount'] . ' ' . $finder['thingsWord'] . '.') ?></span>
            <a class="finder__reset" href="<?= $this->e($finder['action']) ?>">Show everything</a>
        </p>
    </div>
<?php endif; ?>
