<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\ProfileService;

/**
 * Handles the star: making one of your creatures a favourite, or not.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHY THE STAR EXISTS AT ALL. Favourites used to be set only from the profile
 * edit page, as a list of tick-boxes — which meant that to say "this one is my
 * favourite" you had to leave the creature, find the settings page, and pick it
 * out of a list by name. The thing you were pointing at was not on screen.
 *
 * It matters more now than it did: the favourite is the creature on the keepsake
 * card, which is on every page, so choosing one is the difference between having
 * that card and not. A feature reached only through a settings page is a feature
 * most people never find.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6):
 *
 * 1. WHO IS ALLOWED? The creature's owner, logged in, with a valid CSRF token.
 *    Checked here, and again by ProfileService::toggleFavourite(), which only
 *    ever writes ids the player owns — so a forged id changes nothing even if the
 *    check below were removed.
 *
 * 2. WHAT IS THE WORST A MALICIOUS USER COULD DO? Star somebody else's creature,
 *    by putting its id in the address. Refused twice over, and refused with the
 *    same silence as a creature that does not exist. Or flood their own page with
 *    favourites — capped by config, in ProfileService, which is the same cap the
 *    profile form obeys because it is the same method.
 *
 *    Starring cannot expose a private creature: the public page filters for
 *    public creatures at the point of DISPLAY, not here (see
 *    CreatureRepository::findForProfile), so a creature made private later stops
 *    appearing without anybody having to remember to un-star it.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? FavouriteControllerTest proves a stranger
 *    cannot star a creature, that a missing token changes nothing, that the
 *    toggle really goes both ways, and that the cap refuses the seventh.
 */
final class FavouriteController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Session $session,
        private Csrf $csrf,
        private CreatureRepository $creatures,
        private ProfileService $profiles,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    /**
     * @param array<string, string> $parameters Captured route parameters.
     */
    public function toggle(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);

        // Where to go back to. The star appears on a creature's own page and on
        // every card in the collection, so this is an ALLOW-LIST of two — never an
        // address the browser sent, which would be an open redirect.
        $backTo = $request->input('from') === 'collection'
            ? '/creatures'
            : '/creature/' . $creatureId;

        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($backTo);
        }

        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return Response::redirect('/');
        }

        // Only the owner may star a creature. Same wording as "no such creature"
        // would produce, so nothing is revealed about whose it is.
        if ($creature->ownerId !== $userId) {
            return Response::redirect('/');
        }

        if (!$this->rateLimiter->isAllowed(
            'favourite',
            $request->clientIp(),
            $this->rateLimit['max_attempts'],
            $this->rateLimit['window_seconds']
        )) {
            $this->session->flash('That is a lot of rearranging — give it a moment.');
            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('favourite', $request->clientIp());

        $result = $this->profiles->toggleFavourite($userId, $creatureId, $creature->name);
        $this->session->flash($result->message());

        return Response::redirect($backTo);
    }
}
