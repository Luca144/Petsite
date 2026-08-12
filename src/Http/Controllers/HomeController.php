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
 * Shows the home page.
 *
 * @package Felkyo\Http\Controllers
 *
 * For a logged-out visitor this is the welcome page. For a logged-in player it
 * also lists their own creatures, each linking to that creature's page — which is
 * how someone gets from "I just registered" to "here is my creature".
 */
final class HomeController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private CreatureRepository $creatures,
        // Builds the same creature summaries the collection page uses, so home
        // shows real portrait cards rather than a bare list of names.
        private CreatureProfileBuilder $profileBuilder,
        private string $appName,
    ) {
    }

    public function show(Request $request): Response
    {
        // Load the visitor's own creatures if they are logged in; a guest has none.
        // The summaries add each creature's species, level and stage — everything
        // the shared creature-card partial needs to draw a proper portrait card.
        $summaries = [];
        $userId = $this->session->get('user_id');
        if (is_int($userId)) {
            $summaries = $this->profileBuilder->summariesFor($this->creatures->findByOwner($userId));
        }

        $html = $this->templates->render('pages/hello', [
            'appName' => $this->appName,
            'summaries' => $summaries,
        ]);

        return Response::html($html);
    }
}
