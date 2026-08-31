<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Exploration\AreaRepository;
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
 * Exploring grants rewards, so searching is a logged-in, CSRF-protected POST.
 * Areas are content from the DATABASE since M2.5 (built in the creator's
 * panel; they lived in config before) — this one controller drives them all,
 * so a new area needs no new controller code. Only PUBLISHED areas exist as
 * far as players are concerned: the repository's player-facing reads filter
 * for that, so a draft's address redirects exactly like a made-up one.
 */
final class ExplorationController
{
    /**
     * @param array{rate_limit: array{max_attempts: int, window_seconds: int}} $config
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private ExplorationService $exploration,
        private RateLimiter $rateLimiter,
        private AreaRepository $areas,
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
            'areas' => $this->areas->allPublished(),
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

        $area = $this->areas->findBySlugPublished($parameters['area'] ?? '');
        if ($area === null) {
            return Response::redirect('/explore');
        }

        return Response::html($this->templates->render('pages/explore-area', [
            'area' => $area,
            'remaining' => $this->exploration->remainingClicks($userId, $area->slug),
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

        $area = $this->areas->findBySlugPublished($parameters['area'] ?? '');
        if ($area === null) {
            return Response::redirect('/explore');
        }

        $areaPath = '/explore/' . $area->slug;

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

        // The service eats the same array shape the config used to provide;
        // lootFor() hands the rows over service-ready (item slugs joined in).
        $result = $this->exploration->explore($userId, $area->slug, [
            'loot' => $this->areas->lootFor($area->id),
        ]);
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
