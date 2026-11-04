<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Auth\Session;
use Felkyo\Character\BadgeEditorService;
use Felkyo\Character\BadgeRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The panel's badge editor.
 *
 *   GET  /admin/badges                     every badge + a new one
 *   POST /admin/badges/{id}                save (id 0 = new)
 *   POST /admin/badges/{id}/delete
 *   GET  /admin/badges/{id}/picture        picker (art kind "badge")
 *   POST /admin/badges/{id}/picture
 *
 * @package Felkyo\Http\Controllers
 *
 * Behind AdminGate (artist role). Every POST: user, CSRF, content-save rate
 * limit, one BadgeEditorService call.
 */
final class AdminBadgesController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private BadgeRepository $badges,
        private ArtRepository $art,
        private BadgeEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-badges', ['badges' => $this->badges->all()]));
    }

    public function save(Request $request, array $parameters): Response
    {
        return $this->handle($request, fn (User $actor) => $this->editor->save(
            $actor, (int) ($parameters['id'] ?? 0), $request->input('name'), $request->input('description'),
            $request->input('awarded_to'), (int) $request->input('kept_days', '0'), $request->clientIp()
        ));
    }

    public function delete(Request $request, array $parameters): Response
    {
        return $this->handle($request, fn (User $actor) =>
            $this->editor->delete($actor, (int) ($parameters['id'] ?? 0), $request->clientIp()));
    }

    public function pickPicture(Request $request, array $parameters): Response
    {
        $badge = $this->badges->findById((int) ($parameters['id'] ?? 0));
        if ($badge === null) {
            return Response::redirect('/admin/badges');
        }

        return Response::html($this->templates->render('pages/admin-art-picker', [
            'heading' => 'A picture for the "' . $badge['name'] . '" badge',
            'wantedKind' => ArtKind::Badge,
            'choices' => $this->art->ofKind(ArtKind::Badge),
            'currentArtId' => $badge['art_id'],
            'action' => '/admin/badges/' . $badge['id'] . '/picture',
            'backTo' => '/admin/badges',
        ]));
    }

    public function choosePicture(Request $request, array $parameters): Response
    {
        return $this->handle($request, fn (User $actor) => $this->editor->choosePicture(
            $actor, (int) ($parameters['id'] ?? 0), (int) $request->input('art_id', '0'), $request->clientIp()
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

            return Response::redirect('/admin/badges');
        }
        $bucket = 'user:' . $actor->id;
        $limit = $this->saveRateLimit;
        if (!$this->rateLimiter->isAllowed('content_save', $bucket, $limit['max_attempts'], $limit['window_seconds'])) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect('/admin/badges');
        }
        $this->rateLimiter->record('content_save', $bucket);
        $this->session->flash($action($actor)->message);

        return Response::redirect('/admin/badges');
    }
}
