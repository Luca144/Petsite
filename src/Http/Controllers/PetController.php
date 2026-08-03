<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;

/**
 * Handles the "pet this creature" action.
 *
 * @package Felkyo\Http\Controllers
 *
 * Petting changes data, so it is a POST protected by a CSRF token, and only a
 * logged-in visitor may do it. After petting we redirect back to the creature's
 * page (the Post/Redirect/Get pattern) so a page refresh does not pet again; the
 * result ("You petted Biscuit!" or a cooldown notice) is carried across as a
 * one-time flash message.
 *
 * @param array{max_attempts: int, window_seconds: int} handled via $petRateLimit
 */
final class PetController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $petRateLimit
     */
    public function __construct(
        private Session $session,
        private Csrf $csrf,
        private CreatureRepository $creatures,
        private PettingService $petting,
        private RateLimiter $rateLimiter,
        private array $petRateLimit,
    ) {
    }

    /**
     * @param array<string, string> $parameters Captured route parameters.
     */
    public function pet(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $creaturePath = '/creature/' . $creatureId;

        // Must be logged in to pet.
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        // The form must carry a valid CSRF token.
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($creaturePath);
        }

        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return Response::redirect('/');
        }

        // You can only pet a creature you are allowed to see.
        if (!$creature->isPublic && $creature->ownerId !== $userId) {
            return Response::redirect('/');
        }

        // Anti-abuse limit on how fast one IP can pet (the per-creature cooldown
        // inside PettingService is the main gate; this caps mass-petting).
        if (!$this->rateLimiter->isAllowed('pet', $request->clientIp(), $this->petRateLimit['max_attempts'], $this->petRateLimit['window_seconds'])) {
            $this->session->flash('You are petting a little too fast — take a breather.');
            return Response::redirect($creaturePath);
        }
        $this->rateLimiter->record('pet', $request->clientIp());

        // Do the pet (this enforces the per-person, per-creature cooldown) and
        // carry its message back to the creature page.
        $result = $this->petting->pet($userId, $creature);
        $this->session->flash($result->message());

        return Response::redirect($creaturePath);
    }
}
