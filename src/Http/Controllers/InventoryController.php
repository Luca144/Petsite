<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * Shows the logged-in player's inventory, grouped by item category.
 *
 * @package Felkyo\Http\Controllers
 *
 * The grouping is done here so the template just loops over ready-made groups.
 * Because a category is data, a brand-new kind of item appears as a new group,
 * in the artist's chosen order, with nobody touching this file.
 */
final class InventoryController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private InventoryRepository $inventory,
    ) {
    }

    public function show(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        return Response::html($this->templates->render('pages/inventory', [
            'groups' => $this->groupByCategory($this->inventory->findForUser($userId)),
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
