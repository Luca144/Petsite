<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Exploration\ExplorationService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * Handles exploring areas: the list of areas, an area's scene, and searching it.
 *
 * @package Felkyo\Http\Controllers
 *
 * Exploring grants rewards, so searching is a logged-in, CSRF-protected POST. The
 * areas themselves are content from config — this one controller drives them all,
 * so a new area needs no new controller code.
 */
final class ExplorationController
{
    /**
     * @param array{areas: array<string, array>, rate_limit: array{max_attempts: int, window_seconds: int}} $config
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private ExplorationService $exploration,
        private RateLimiter $rateLimiter,
        private array $config,
    ) {
    }

    /**
     * List the areas a player can explore.
     */
    public function index(Request $request): Response
    {
        if (!$this->requireLoginId()) {
            return Response::redirect('/login');
        }

        return Response::html($this->templates->render('pages/explore-index', [
            'areas' => $this->config['areas'],
        ]));
    }

    /**
     * Show one area's scene, with its clickable spots and searches remaining.
     *
     * @param array<string, string> $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        $userId = $this->requireLoginId();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $areaSlug = $parameters['area'] ?? '';
        $area = $this->config['areas'][$areaSlug] ?? null;
        if ($area === null) {
            return Response::redirect('/explore');
        }

        return Response::html($this->templates->render('pages/explore-area', [
            'areaSlug' => $areaSlug,
            'area' => $area,
            'remaining' => $this->exploration->remainingClicks($userId, $areaSlug),
        ]));
    }

    /**
     * Search the area once (one click). Login + CSRF required.
     *
     * @param array<string, string> $parameters
     */
    public function search(Request $request, array $parameters): Response
    {
        $userId = $this->requireLoginId();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $areaSlug = $parameters['area'] ?? '';
        $area = $this->config['areas'][$areaSlug] ?? null;
        if ($area === null) {
            return Response::redirect('/explore');
        }

        $areaPath = '/explore/' . $areaSlug;

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($areaPath);
        }

        // Anti-abuse IP limit (the per-visit click limit is the main gate).
        $limit = $this->config['rate_limit'];
        if (!$this->rateLimiter->isAllowed('explore', $request->clientIp(), $limit['max_attempts'], $limit['window_seconds'])) {
            $this->session->flash('You are searching a little too fast — take a breath.');
            return Response::redirect($areaPath);
        }
        $this->rateLimiter->record('explore', $request->clientIp());

        $result = $this->exploration->explore($userId, $areaSlug, $area);
        $this->session->flash($result->message());

        return Response::redirect($areaPath);
    }

    /**
     * The logged-in user's id, or null if they are not logged in.
     */
    private function requireLoginId(): ?int
    {
        $userId = $this->session->get('user_id');

        return is_int($userId) ? $userId : null;
    }
}
