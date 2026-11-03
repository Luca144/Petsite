<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Themes\ThemeRepository;
use League\Plates\Engine;

/**
 * Choosing how the site looks for you (M4.1, M4.3).
 *
 *   GET  /themes   every published theme as a tappable card with swatches
 *   POST /themes   choose one (theme_id = 0 means the site's own look)
 *
 * @package Felkyo\Http\Controllers
 *
 * Logged-in players only. Only a PUBLISHED theme can be chosen — enforced in
 * ThemeRepository's SQL — so posting a draft's id does nothing.
 */
final class ThemeController
{
    /** @param array{max_attempts: int, window_seconds: int} $rateLimit */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private ThemeRepository $themes,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        return Response::html($this->templates->render('pages/themes', [
            'themes' => $this->themes->published(),
            'chosenId' => $this->themes->forUser($userId)?->id,
        ]));
    }

    public function choose(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/themes');
        }
        if (!$this->rateLimiter->isAllowed('theme', 'user:' . $userId, $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('That\'s a lot of redecorating — give it a moment.');

            return Response::redirect('/themes');
        }
        $this->rateLimiter->record('theme', 'user:' . $userId);

        $themeId = (int) $request->input('theme_id', '0');
        $chosen = $this->themes->chooseForUser($userId, $themeId === 0 ? null : $themeId);
        $this->session->flash($chosen ? 'Your new look is on. Change it any time.' : 'That theme isn\'t available.');

        return Response::redirect('/themes');
    }
}
