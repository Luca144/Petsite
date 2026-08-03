<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
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
        private string $appName,
    ) {
    }

    public function show(Request $request): Response
    {
        // Load the visitor's own creatures if they are logged in; a guest has none.
        $creatures = [];
        $userId = $this->session->get('user_id');
        if (is_int($userId)) {
            $creatures = $this->creatures->findByOwner($userId);
        }

        $html = $this->templates->render('pages/hello', [
            'appName' => $this->appName,
            'creatures' => $creatures,
        ]);

        return Response::html($html);
    }
}
