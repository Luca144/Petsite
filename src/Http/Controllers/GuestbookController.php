<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Guestbook\GuestbookService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;

/**
 * Handles someone signing a creature's guestbook.
 *
 * @package Felkyo\Http\Controllers
 *
 * This controller answers "is this person ALLOWED to sign this guestbook at all?"
 * — they must be logged in, send a valid CSRF token, and be able to see the
 * creature. Whether the signing itself is valid (a real message, the one-per-person
 * rule, the once-a-day change) is GuestbookService's job. Keeping the two apart
 * means the rules of the game can change without touching web plumbing, and vice
 * versa.
 *
 * Signing changes state, so it is a POST and follows Post/Redirect/Get: do the
 * work, leave a flash message, redirect back to the creature. That way a browser
 * refresh cannot accidentally submit the form a second time.
 */
final class GuestbookController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Session $session,
        private Csrf $csrf,
        private CreatureRepository $creatures,
        private GuestbookService $guestbook,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    /**
     * @param array<string, string> $parameters Captured route parameters.
     */
    public function sign(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $creaturePath = '/creature/' . $creatureId;

        // Only logged-in visitors can sign — we need to know who they are to keep
        // the "one entry per person" promise.
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($creaturePath);
        }

        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return Response::redirect('/');
        }

        // You may only sign a guestbook you are allowed to see. A private creature
        // is visible to its owner alone, so nobody else may sign it either — we
        // send them to the home page rather than confirm the creature exists.
        if (!$creature->isPublic && $creature->ownerId !== $userId) {
            return Response::redirect('/');
        }

        // Anti-abuse limit per IP. The one-entry-per-creature rule is the real
        // gate; this just stops someone hammering the endpoint (CLAUDE.md §6).
        if (!$this->rateLimiter->isAllowed('guestbook', $request->clientIp(), $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('You are signing a little too fast — please slow down.');
            return Response::redirect($creaturePath);
        }
        $this->rateLimiter->record('guestbook', $request->clientIp());

        $result = $this->guestbook->sign($userId, $creature, $request->input('message_key'));
        $this->session->flash($result->message());

        return Response::redirect($creaturePath);
    }
}
