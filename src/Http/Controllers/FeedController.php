<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\FeedingService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;

/**
 * Handles "give this creature a treat".
 *
 * @package Felkyo\Http\Controllers
 *
 * Feeding changes data, so it is a POST protected by a CSRF token and open only
 * to a logged-in visitor. Afterwards we redirect back to wherever the button was
 * pressed (Post/Redirect/Get), so a refresh does not feed again, and the result
 * travels as a one-time flash message.
 *
 * WHERE IT REDIRECTS TO, AND WHY THAT IS NOT TAKEN FROM THE REQUEST. The button
 * appears in two places — the creature's own page and the sidebar card, which is
 * on every page — so "back where I was" is a real question. It is answered from
 * a short ALLOW-LIST below, never by redirecting to an address the browser sent.
 * A redirect that trusts a submitted URL is an open redirect: somebody links a
 * player to a form that posts here and lands them on a copy of the site asking
 * for their password. The whole feature is two buttons; it is not worth that.
 *
 * All the decisions about whether feeding is ALLOWED live in FeedingService —
 * read that class's docblock for who may feed what, and what stops it being
 * abused. This controller only carries the request in and the answer out.
 */
final class FeedController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $feedRateLimit
     */
    public function __construct(
        private Session $session,
        private Csrf $csrf,
        private CreatureRepository $creatures,
        private FeedingService $feeding,
        private RateLimiter $rateLimiter,
        private array $feedRateLimit,
    ) {
    }

    /**
     * @param array<string, string> $parameters Captured route parameters.
     */
    public function feed(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $backTo = $this->returnPathFor($request, $creatureId);

        // Must be logged in to feed.
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        // The form must carry a valid CSRF token.
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($backTo);
        }

        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return Response::redirect('/');
        }

        // Anti-abuse limit on how fast one address can feed. The real gates are in
        // FeedingService — you must own the creature and hold the treat, and each
        // treat is consumed — so this is only here to keep a script from hammering
        // the endpoint, as every state-changing route does (CLAUDE.md section 6).
        if (!$this->rateLimiter->isAllowed(
            'feed',
            $request->clientIp(),
            $this->feedRateLimit['max_attempts'],
            $this->feedRateLimit['window_seconds']
        )) {
            $this->session->flash('That is a lot of snacks at once — give it a moment.');
            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('feed', $request->clientIp());

        $result = $this->feeding->feed($userId, $creature, (int) $request->input('item_id'));
        $this->session->flash($result->message());

        // A real feed plays the little celebration once, the same way a pet does.
        if ($result->isSuccessful()) {
            $this->session->celebrate('pet');
        }

        return Response::redirect($backTo);
    }

    /**
     * Where to send the player afterwards.
     *
     * The form says which of two places it was pressed in, and this turns that
     * into one of two addresses WE choose. Nothing the browser sends is ever used
     * as a redirect target — see the class docblock for why that matters. Anything
     * unrecognised goes to the creature's page, which is always a sensible place
     * to end up.
     */
    private function returnPathFor(Request $request, int $creatureId): string
    {
        if ($request->input('from') === 'home') {
            return '/';
        }

        return '/creature/' . $creatureId;
    }
}
