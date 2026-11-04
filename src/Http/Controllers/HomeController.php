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
        // The latest site updates for the home page (optional for old wiring).
        private ?\Felkyo\Library\ArticleRepository $articles = null,
        // The first-run welcome (M7) and the shop whose keeper gives it.
        private ?\Felkyo\Users\WelcomeRepository $welcome = null,
        private ?\Felkyo\Economy\ShopRepository $shops = null,
        private string $keeperShopSlug = 'general-store',
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
            // The two newest site updates, between the welcome and the
            // creatures (Skerro, 2026-08-30) — for guests too.
            'updates' => $this->articles?->published(2, \Felkyo\Library\Article::KIND_UPDATE) ?? [],
            'welcome' => is_int($userId) ? $this->welcomeFor($userId, (int) $request->query('welcome', '0')) : null,
        ]);

        return Response::html($html);
    }

    /**
     * Ruinily's welcome for this player, or null once they've dismissed it.
     * Her name and portrait come from the shop she keeps, so changing the
     * keeper in the panel changes who welcomes people too.
     */
    private function welcomeFor(int $userId, int $step): ?array
    {
        if ($this->welcome === null || $this->welcome->isDismissed($userId)) {
            return null;
        }
        $shop = $this->shops?->findBySlug($this->keeperShopSlug);

        return [
            'steps' => $this->welcome->steps(),
            'step' => $step,
            'keeperName' => $shop?->keeperName ?? 'Ruinily',
            'portraitUrl' => $shop?->portraitUrl(),
        ];
    }
}
