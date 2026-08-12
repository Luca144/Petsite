<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\ProfileRepository;
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
 *    an account puts a rate-limitable identity behind every query.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO? Build a list of everybody
 *    here, and work through it. That is the whole threat, and it is answered in
 *    four places rather than one:
 *      - PREFIX matching only, so a common letter does not return a slice of the
 *        site (see ProfileRepository::searchByNamePrefix).
 *      - A MINIMUM of two characters, so one letter is not a listing.
 *      - A SMALL RESULT CAP, so a wide prefix cannot be harvested in one request.
 *      - RATE LIMITING, so working through the alphabet is slow and visible.
 *    And no endpoint anywhere returns players by recency or in bulk — there is no
 *    "newest members" list on this site, deliberately.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? SearchControllerTest proves each: a short
 *    query returns nothing, an unfindable player never appears, a middle-of-name
 *    match does not, results are capped, the limit bites, and no response ever
 *    contains an email address.
 */
final class SearchController
{
    /**
     * @param array{minimum_length: int, result_limit: int} $limits
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private ProfileRepository $profiles,
        private AvatarSet $avatars,
        private RateLimiter $rateLimiter,
        private array $limits,
        private array $rateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        if (!is_int($this->session->get('user_id'))) {
            return Response::redirect('/login');
        }

        $query = trim($request->input('q'));

        // An empty box is the first visit, not a failed search — so it gets an
        // invitation rather than "no results found".
        if ($query === '') {
            return $this->page($query, null);
        }

        if (mb_strlen($query) < $this->limits['minimum_length']) {
            return $this->page($query, null, sprintf(
                'Please type at least %d letters.',
                $this->limits['minimum_length']
            ));
        }

        if (!$this->rateLimiter->isAllowed('search', $request->clientIp(), $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            return $this->page($query, null, 'That is a lot of searching — please slow down a little.');
        }
        $this->rateLimiter->record('search', $request->clientIp());

        return $this->page($query, $this->profiles->searchByNamePrefix($query, $this->limits['result_limit']));
    }

    /**
     * Render the search page.
     *
     * @param array<int, \Felkyo\Users\Profile>|null $results Null means "no search
     *        has been run", which is a different thing from "no matches" and gets
     *        a different, warmer message.
     */
    private function page(string $query, ?array $results, ?string $notice = null): Response
    {
        // The avatar path is resolved here so the template never learns how to
        // turn a key into a file — that stays in one place.
        $found = [];
        foreach ($results ?? [] as $profile) {
            $found[] = [
                'username' => $profile->username,
                'avatarPath' => $this->avatars->imagePathFor($profile->avatarKey),
                'avatarName' => $this->avatars->nameFor($profile->avatarKey),
            ];
        }

        return Response::html($this->templates->render('pages/search', [
            'query' => $query,
            'results' => $found,
            'hasSearched' => $results !== null,
            'notice' => $notice,
            'minimumLength' => $this->limits['minimum_length'],
        ]));
    }
}
