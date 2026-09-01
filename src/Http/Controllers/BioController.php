<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Moderation\MutedVoice;
use Felkyo\Security\RateLimiter;

/**
 * Handles saving a creature's bio (owner only).
 *
 * @package Felkyo\Http\Controllers
 *
 * This enforces WHO may edit — only the creature's owner. Everyone else is
 * refused, even if they somehow submit the form. Saving is a state-changing POST,
 * so it needs login and a CSRF token, and it follows Post/Redirect/Get with a
 * flash message.
 */
final class BioController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Session $session,
        private Csrf $csrf,
        private CreatureRepository $creatures,
        private CreatureBioService $bioService,
        private MutedVoice $mutedVoice,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    /**
     * @param array<string, string> $parameters
     */
    public function update(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $creaturePath = '/creature/' . $creatureId;

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

        // A muted player may not write new words (M2.7). The same check, with
        // the same kind sentence, guards every free-text door — see MutedVoice.
        $mutedWords = $this->mutedVoice->refusalFor($userId);
        if ($mutedWords !== null) {
            $this->session->flash($mutedWords);

            return Response::redirect($creaturePath);
        }

        // Only the owner may edit the bio. Anyone else is refused.
        if ($creature->ownerId !== $userId) {
            $this->session->flash('You can only edit your own creature\'s bio.');
            return Response::redirect($creaturePath);
        }

        // Anti-abuse limit on how often bios can be saved from one IP.
        if (!$this->rateLimiter->isAllowed('bio', $request->clientIp(), $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('You are editing a little too fast — please slow down.');
            return Response::redirect($creaturePath);
        }
        $this->rateLimiter->record('bio', $request->clientIp());

        $result = $this->bioService->updateBio($creature, $userId, $request->input('bio'));
        $this->session->flash($result->message());

        return Response::redirect($creaturePath);
    }
}
