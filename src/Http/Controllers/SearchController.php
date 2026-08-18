<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Users\PlayerFinder;
use League\Plates\Engine;

/**
 * Finding another player by name.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHY SEARCH IS SAFE HERE IN A WAY IT IS NOT ON MOST SITES: there is nothing
 * harmful you can do with a player once you have found them. No messaging, no
 * private channel, no words of your own. The worst somebody can do with a found
 * profile is send a card that everybody can see. The danger in "finding people"
 * is what comes after finding them, and on this site that door is already shut.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6):
 *
 * 1. WHO IS ALLOWED? Any logged-in player. Logged-out visitors are not offered
 *    search — not because a name is secret, but because an open search box is the
 *    cheapest possible way to script your way through a playerbase, and requiring
 *    an account puts a rate-limitable identity behind every query. That check is
 *    below, and it is this controller's own job.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO? Build a list of everybody
 *    here, and work through it. That threat is answered by PlayerFinder, which
 *    owns the minimum length, the result cap and the rate limit — read the class
 *    docblock there for why those rules live in one place rather than in each
 *    page that searches.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? SearchControllerTest proves each: a short
 *    query returns nothing, an unfindable player never appears, a middle-of-name
 *    match does not, results are capped, the limit bites, and no response ever
 *    contains an email address.
 */
final class SearchController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private PlayerFinder $finder,
        private int $minimumLength,
    ) {
    }

    public function show(Request $request): Response
    {
        if (!is_int($this->session->get('user_id'))) {
            return Response::redirect('/login');
        }

        $result = $this->finder->find($request->query('q'), $request->clientIp());

        return Response::html($this->templates->render('pages/search', [
            'query' => $result->query,
            'results' => $result->players,
            'hasSearched' => $result->hasSearched,
            'notice' => $result->notice,
            'minimumLength' => $this->minimumLength,
        ]));
    }
}
