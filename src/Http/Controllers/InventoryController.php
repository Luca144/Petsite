<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * Shows the logged-in player's inventory, grouped by item type.
 *
 * @package Felkyo\Http\Controllers
 *
 * The grouping is done here so the template just loops over ready-made groups.
 * Because items carry their own "type", new types appear as new groups
 * automatically — no code change needed for a new kind of item.
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

        // Group the owned items by their type, e.g. "sticker" => [ ... ].
        $groups = [];
        foreach ($this->inventory->findForUser($userId) as $entry) {
            $groups[$entry['item']->type][] = $entry;
        }

        return Response::html($this->templates->render('pages/inventory', [
            'groups' => $groups,
        ]));
    }
}
