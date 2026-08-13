<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ItemFinder;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * Shows the logged-in player's inventory, with the finder row to narrow it.
 *
 * @package Felkyo\Http\Controllers
 *
 * SECURITY SHAPE (docs/plan/2026-08-13-usability-pass.md): everything shown
 * comes from findForUser($sessionUserId) — the list is scoped to its owner
 * before any filter runs, so no URL parameter can reach another player's
 * things. The filters themselves are read-only narrowing of that list.
 */
final class InventoryController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private InventoryRepository $inventory,
        private ItemFinder $finder,
        // Show the search box from this many piles up (config; a search box
        // floating over four items is clutter, not help).
        private int $searchShownFrom,
    ) {
    }

    public function show(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        // Everything the player owns, then what the finder row narrows it to.
        // The category value from the URL is only accepted if it names a
        // category actually present; anything else means "everything".
        $allStacks = $this->inventory->findForUser($userId);
        $categories = $this->finder->categoriesOfStacks($allStacks);
        $categorySlug = $this->finder->validCategorySlug($request->query('category'), $categories);
        $searchText = $this->finder->cleanSearchText($request->query('q'));
        $stacks = $this->finder->filterStacks($allStacks, $categorySlug, $searchText);

        return Response::html($this->templates->render('pages/inventory', [
            'groups' => $this->groupByCategory($stacks),
            'isFiltered' => $categorySlug !== '' || $searchText !== '',
            'stacks' => $stacks,
            'finder' => [
                'action' => '/inventory',
                'categories' => $categories,
                'activeSlug' => $categorySlug,
                'searchText' => $searchText,
                'totalCount' => count($allStacks),
                'shownCount' => count($stacks),
                'searchShownFrom' => $this->searchShownFrom,
                'thingsWord' => 'things',
            ],
        ]));
    }

    /**
     * Turn a flat list of piles into one group per category.
     *
     * The repository already returns them in the artist's chosen category order,
     * so walking the list in order and starting a new group whenever the category
     * changes keeps that order without sorting anything again here.
     *
     * @param  array<int, \Felkyo\Economy\OwnedItemStack> $stacks
     * @return array<int, array{category: \Felkyo\Economy\ItemCategory, stacks: array<int, \Felkyo\Economy\OwnedItemStack>}>
     */
    private function groupByCategory(array $stacks): array
    {
        $groups = [];

        foreach ($stacks as $stack) {
            $slug = $stack->item->category->slug;

            // First time we meet a category, open a group for it and remember the
            // category itself — the heading needs its name, icon and colour, not
            // just its slug.
            if (!isset($groups[$slug])) {
                $groups[$slug] = ['category' => $stack->item->category, 'stacks' => []];
            }

            $groups[$slug]['stacks'][] = $stack;
        }

        // The slugs were only ever keys for the grouping; the template wants a
        // plain list it can loop over.
        return array_values($groups);
    }
}
