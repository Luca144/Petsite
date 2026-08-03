<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\AdoptionService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * Handles daily adoption: the page with the "Adopt" button, and the action.
 *
 * @package Felkyo\Http\Controllers
 *
 * show() displays whether the player can adopt today. adopt() performs it: it is
 * a state-changing POST, so it needs a CSRF token and a logged-in visitor, and it
 * follows the Post/Redirect/Get pattern with a flash message.
 */
final class AdoptionController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $adoptRateLimit
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private AdoptionService $adoption,
        private RateLimiter $rateLimiter,
        private array $adoptRateLimit,
    ) {
    }

    /**
     * Show the adoption page. Requires login.
     */
    public function show(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        $html = $this->templates->render('pages/adopt', [
            'canAdopt' => $this->adoption->canAdopt($userId),
            'flash' => $this->session->takeFlash(),
        ]);

        return Response::html($html);
    }

    /**
     * Perform an adoption. Requires login and a valid CSRF token.
     */
    public function adopt(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect('/adopt');
        }

        // Anti-abuse IP limit (the once-per-day cooldown is the main gate).
        if (!$this->rateLimiter->isAllowed('adopt', $request->clientIp(), $this->adoptRateLimit['max_attempts'], $this->adoptRateLimit['window_seconds'])) {
            $this->session->flash('You are adopting a little too fast — please slow down.');
            return Response::redirect('/adopt');
        }
        $this->rateLimiter->record('adopt', $request->clientIp());

        $result = $this->adoption->adopt($userId);
        $this->session->flash($result->message());

        // On success, take the player straight to their new friend's page.
        if ($result->isSuccessful()) {
            return Response::redirect('/creature/' . $result->creature()->id);
        }

        return Response::redirect('/adopt');
    }
}
