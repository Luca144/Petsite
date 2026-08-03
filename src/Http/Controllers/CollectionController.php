<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * Shows the logged-in player's collection — all the creatures they own.
 *
 * @package Felkyo\Http\Controllers
 *
 * The schema and queries have supported many creatures per user since the start,
 * so this view simply lists them all. It requires the visitor to be logged in.
 */
final class CollectionController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private CreatureRepository $creatures,
        private CreatureProfileBuilder $profileBuilder,
    ) {
    }

    public function show(Request $request): Response
    {
        // You must be logged in to see your own collection.
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        $creatures = $this->creatures->findByOwner($userId);
        // summariesFor gathers each creature's species/level/stage efficiently.
        $summaries = $this->profileBuilder->summariesFor($creatures);

        $html = $this->templates->render('pages/collection', [
            'summaries' => $summaries,
        ]);

        return Response::html($html);
    }
}
