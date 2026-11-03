<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Auth\Session;
use Felkyo\Character\CharacterEditorService;
use Felkyo\Character\LadderRepository;
use Felkyo\Character\TraitRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use League\Plates\Engine;
use Felkyo\Users\UserRepository;

/**
 * The panel's "character" page (M3.2, M3.2c): traits, level titles, ages.
 *
 *   GET  /admin/character                 everything on one page
 *   POST /admin/character/traits/{id}     save a trait (id 0 = a new one)
 *   POST /admin/character/traits/{id}/delete
 *   POST /admin/character/ladder/{ladder} replace a ladder from its lines
 *
 * @package Felkyo\Http\Controllers
 *
 * Behind AdminGate (artist role). Every POST: user, CSRF, the shared
 * content-save rate limit, one service call, back to this page.
 */
final class AdminCharacterController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private TraitRepository $traits,
        private LadderRepository $ladders,
        private CharacterEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-character', [
            'traits' => $this->traits->all(),
            'levelTitles' => $this->ladders->rungs(LadderRepository::LEVEL_TITLE),
            'ages' => $this->ladders->rungs(LadderRepository::AGE),
        ]));
    }

    public function saveTrait(Request $request, array $parameters): Response
    {
        $fields = [];
        foreach (['name', 'description', 'tier', 'earn_kind', 'earn_threshold', 'chance_percent', 'xp_bonus_percent'] as $key) {
            $fields[$key] = $request->input($key);
        }

        return $this->handle($request, fn (User $actor) => $this->editor->saveTrait(
            $actor, (int) ($parameters['id'] ?? 0), $fields, $request->clientIp()
        ));
    }

    public function deleteTrait(Request $request, array $parameters): Response
    {
        return $this->handle($request, fn (User $actor) =>
            $this->editor->deleteTrait($actor, (int) ($parameters['id'] ?? 0), $request->clientIp()));
    }

    public function saveLadder(Request $request, array $parameters): Response
    {
        return $this->handle($request, fn (User $actor) => $this->editor->saveLadder(
            $actor, (string) ($parameters['ladder'] ?? ''), $request->input('lines'), $request->clientIp()
        ));
    }

    /** @param callable(User): ContentEditResult $action */
    private function handle(Request $request, callable $action): Response
    {
        $actor = $this->users->findById((int) $this->session->get('user_id'));
        if ($actor === null) {
            return Response::redirect('/login');
        }
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/character');
        }

        $bucket = 'user:' . $actor->id;
        $limit = $this->saveRateLimit;
        if (!$this->rateLimiter->isAllowed('content_save', $bucket, $limit['max_attempts'], $limit['window_seconds'])) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect('/admin/character');
        }
        $this->rateLimiter->record('content_save', $bucket);

        $this->session->flash($action($actor)->message);

        return Response::redirect('/admin/character');
    }
}
