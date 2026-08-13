<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Economy\Item;
use Felkyo\Economy\ItemCategory;
use Felkyo\Economy\ItemFinder;
use Felkyo\Economy\OwnedItemStack;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ItemFinder — the category and name filters behind the finder row.
 *
 * @package Felkyo\Tests\Unit
 *
 * These also stand in for the security promises in the plan
 * (docs/plan/2026-08-13-usability-pass.md): hostile search text is capped and
 * inert, and an unknown category quietly means "everything".
 */
final class ItemFinderTest extends TestCase
{
    private ItemFinder $finder;

    /** @var array<int, Item> */
    private array $items;

    protected function setUp(): void
    {
        $this->finder = new ItemFinder();

        $dish = new ItemCategory(1, 'dish', 'Dish', '--category-dish', 'bowl', 1);
        $sticker = new ItemCategory(2, 'sticker', 'Sticker', '--category-sticker', 'star', 2);

        $this->items = [
            new Item(1, 'acorn-treat', 'Acorn Treat', null, 8, 4, $dish),
            new Item(2, 'honey-treat', 'Honey Treat', null, 10, 5, $dish),
            new Item(3, 'gold-star', 'Gold Star Sticker', null, 20, 10, $sticker),
        ];
    }

    /** @return array<int, string> The names, for easy whole-result assertions. */
    private function names(array $items): array
    {
        return array_map(static fn (Item $item): string => $item->name, $items);
    }

    public function testNoFiltersMeansEverythingComesBack(): void
    {
        $found = $this->finder->filterItems($this->items, '', '');

        $this->assertCount(3, $found);
    }

    public function testSearchMatchesAnywhereInTheNameIgnoringCase(): void
    {
        // "corn" sits in the middle of "Acorn", and the case is wrong on
        // purpose — a player searching is half-remembering, not quoting.
        $found = $this->finder->filterItems($this->items, '', 'CORN');

        $this->assertSame(['Acorn Treat'], $this->names($found));
    }

    public function testACategoryKeepsOnlyItsOwnItems(): void
    {
        $found = $this->finder->filterItems($this->items, 'dish', '');

        $this->assertSame(['Acorn Treat', 'Honey Treat'], $this->names($found));
    }

    public function testCategoryAndSearchCombine(): void
    {
        // "treat" alone matches two items; with the sticker category it must
        // match none — the filters narrow together, not compete.
        $found = $this->finder->filterItems($this->items, 'sticker', 'treat');

        $this->assertSame([], $found);
    }

    public function testAnUnknownCategoryMeansEverything(): void
    {
        // A stale or hostile ?category= value must not error and must not hide
        // the player's things — it simply does not filter.
        $found = $this->finder->filterItems($this->items, 'no-such-category', '');

        $this->assertCount(3, $found);
    }

    public function testWhitespaceOnlySearchDoesNotFilter(): void
    {
        $found = $this->finder->filterItems($this->items, '', '   ');

        $this->assertCount(3, $found);
    }

    public function testOverlongSearchTextIsCappedNotFatal(): void
    {
        // 500 characters of the letter a: capped to 40 and matched normally —
        // meaning it matches nothing, but nothing breaks either.
        $found = $this->finder->filterItems($this->items, '', str_repeat('a', 500));

        $this->assertSame([], $found);
        $this->assertSame(40, mb_strlen($this->finder->cleanSearchText(str_repeat('a', 500))));
    }

    public function testStacksFilterTheSameWayAsItems(): void
    {
        $stacks = array_map(static fn (Item $item): OwnedItemStack => new OwnedItemStack($item, 2), $this->items);

        $found = $this->finder->filterStacks($stacks, 'dish', 'honey');

        $this->assertCount(1, $found);
        $this->assertSame('Honey Treat', $found[0]->item->name);
    }

    public function testCategoriesAreSummarisedWithCounts(): void
    {
        $categories = $this->finder->categoriesOfItems($this->items);

        $this->assertSame(
            [['dish', 2], ['sticker', 1]],
            array_map(static fn (array $category): array => [$category['slug'], $category['count']], $categories)
        );
    }

    public function testOnlyAPresentCategorySlugIsAccepted(): void
    {
        $categories = $this->finder->categoriesOfItems($this->items);

        $this->assertSame('dish', $this->finder->validCategorySlug('dish', $categories));
        $this->assertSame('', $this->finder->validCategorySlug('weapon', $categories));
    }
}
