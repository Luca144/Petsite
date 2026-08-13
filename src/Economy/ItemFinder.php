<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * Narrows a list of items (or owned piles) by category and by a name search.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: the one place that understands the "finder row" both economy
 * pages share — the category pills and the search box on the inventory and the
 * shop. Both controllers hand their already-fetched list to this class, so the
 * two pages can never drift into filtering by different rules.
 *
 * WHY IT FILTERS IN PHP AND NOT IN SQL: the lists are small (one player's
 * things; one shop's stock) and already loaded. Filtering here means no new
 * query surface, nothing a hostile parameter could reach — the search text is
 * never concatenated into anything, it is only compared against names. If a
 * later milestone brings lists too big to load whole, only the inside of this
 * class needs to change; its inputs (a category slug and a search text) are
 * already what a SQL version would take.
 *
 * INPUT RULES (the same for every caller):
 * - the search text is trimmed and capped at MAX_SEARCH_LENGTH characters —
 *   long enough for any real item name, short enough that nobody can post a
 *   novel through the URL;
 * - a category slug that does not match anything simply means "everything".
 *   An unknown value is not an error a player should ever see a page about.
 */
final class ItemFinder
{
    /** The longest search text we will look at. */
    public const MAX_SEARCH_LENGTH = 40;

    /**
     * Tidy a raw search text into what will actually be matched (and echoed
     * back into the search box). Kept public so controllers show the player
     * exactly the text that was used, not the raw thing they sent.
     */
    public function cleanSearchText(string $rawSearchText): string
    {
        return mb_substr(trim($rawSearchText), 0, self::MAX_SEARCH_LENGTH);
    }

    /**
     * Filter a player's owned piles (inventory page).
     *
     * @param  array<int, OwnedItemStack> $stacks
     * @return array<int, OwnedItemStack>
     */
    public function filterStacks(array $stacks, string $categorySlug, string $searchText): array
    {
        // The unknown-category rule is enforced HERE as well as in
        // validCategorySlug(), so even a future caller that forgets to validate
        // first can never blank out a player's whole list with a stale slug.
        $categorySlug = $this->validCategorySlug($categorySlug, $this->categoriesOfStacks($stacks));

        $kept = [];
        foreach ($stacks as $stack) {
            if ($this->matches($stack->item, $categorySlug, $searchText)) {
                $kept[] = $stack;
            }
        }

        return $kept;
    }

    /**
     * Filter a shop's stock (shop page).
     *
     * @param  array<int, Item> $items
     * @return array<int, Item>
     */
    public function filterItems(array $items, string $categorySlug, string $searchText): array
    {
        // Same forgiving rule as filterStacks — see the comment there.
        $categorySlug = $this->validCategorySlug($categorySlug, $this->categoriesOfItems($items));

        $kept = [];
        foreach ($items as $item) {
            if ($this->matches($item, $categorySlug, $searchText)) {
                $kept[] = $item;
            }
        }

        return $kept;
    }

    /**
     * The categories present in a list of piles, each with a count — what the
     * finder row's pills are built from. Order follows the list itself, which
     * the repositories already return in the artist's chosen category order.
     *
     * @param  array<int, OwnedItemStack> $stacks
     * @return array<int, array{slug: string, name: string, iconKey: string, count: int}>
     */
    public function categoriesOfStacks(array $stacks): array
    {
        return $this->summariseCategories(array_map(
            static fn (OwnedItemStack $stack): Item => $stack->item,
            $stacks
        ));
    }

    /**
     * The categories present in a shop's stock, each with a count.
     *
     * @param  array<int, Item> $items
     * @return array<int, array{slug: string, name: string, iconKey: string, count: int}>
     */
    public function categoriesOfItems(array $items): array
    {
        return $this->summariseCategories($items);
    }

    /**
     * A category slug from the URL is only accepted if it actually appears in
     * the list being filtered — anything else means "everything". A player can
     * never see an error page because a link held a stale or hostile value.
     *
     * @param array<int, array{slug: string, name: string, iconKey: string, count: int}> $categories
     */
    public function validCategorySlug(string $requestedSlug, array $categories): string
    {
        foreach ($categories as $category) {
            if ($category['slug'] === $requestedSlug) {
                return $requestedSlug;
            }
        }

        return '';
    }

    /**
     * Count how many entries each category has, keeping first-seen order.
     *
     * @param  array<int, Item> $items
     * @return array<int, array{slug: string, name: string, iconKey: string, count: int}>
     */
    private function summariseCategories(array $items): array
    {
        $categories = [];
        foreach ($items as $item) {
            $slug = $item->category->slug;
            if (!isset($categories[$slug])) {
                $categories[$slug] = [
                    'slug' => $slug,
                    'name' => $item->category->name,
                    'iconKey' => $item->category->iconKey,
                    'count' => 0,
                ];
            }
            $categories[$slug]['count']++;
        }

        return array_values($categories);
    }

    /**
     * Does one item survive both filters?
     *
     * The category must match exactly when one is given; the search text may
     * appear anywhere in the name, in any letter case — "corn" finds the
     * Acorn Treat, because a player searching is half-remembering, not quoting.
     */
    private function matches(Item $item, string $categorySlug, string $searchText): bool
    {
        if ($categorySlug !== '' && $item->category->slug !== $categorySlug) {
            return false;
        }

        $searchText = $this->cleanSearchText($searchText);
        if ($searchText === '') {
            return true;
        }

        return mb_stripos($item->name, $searchText) !== false;
    }
}
