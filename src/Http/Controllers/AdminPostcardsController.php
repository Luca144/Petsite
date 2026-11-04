<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Social\PostcardEditorService;
use Felkyo\Social\PostcardRepository;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The panel's postcard editor: the designs on the post office rack.
 *
 *   GET  /admin/postcards                     every design + start a new one
 *   POST /admin/postcards/create
 *   GET  /admin/postcards/{id}                edit one (words, price, picture)
 *   POST /admin/postcards/{id}/save|publish|unpublish|delete
 *   GET  /admin/postcards/{id}/picture        picker (card art only)
 *   POST /admin/postcards/{id}/picture
 *
 * @package Felkyo\Http\Controllers
 *
 * Behind AdminGate (artist role). Every POST: user, CSRF, content-save rate
 * limit, one service call — the same shape as every panel editor.
 */
final class AdminPostcardsController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private PostcardRepository $postcards,
        private ArtRepository $art,
        private PostcardEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-postcards', [
            'designs' => $this->postcards->allDesigns(),
        ]));
    }

    public function show(Request $request, array $parameters): Response
    {
        $design = $this->postcards->findDesign((int) ($parameters['id'] ?? 0));
        if ($design === null) {
            return Response::redirect('/admin/postcards');
        }

        return Response::html($this->templates->render('pages/admin-postcard-edit', [
            'design' => $design,
            'timesSent' => $this->postcards->timesSent($design['id']),
        ]));
    }

    public function create(Request $request): Response
    {
        return $this->handle($request, null, fn (User $actor) =>
            $this->editor->create($actor, $request->input('name'), $request->clientIp()));
    }

    public function save(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) => $this->editor->save(
            $actor, $id, $request->input('name'), $request->input('message'),
            (int) $request->input('gem_price', '0'), $request->clientIp()
        ));
    }

    public function publish(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) => $this->editor->setPublished($actor, $id, true, $request->clientIp()));
    }

    public function unpublish(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) => $this->editor->setPublished($actor, $id, false, $request->clientIp()));
    }

    public function delete(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) => $this->editor->delete($actor, $id, $request->clientIp()), true);
    }

    public function pickPicture(Request $request, array $parameters): Response
    {
        $design = $this->postcards->findDesign((int) ($parameters['id'] ?? 0));
        if ($design === null) {
            return Response::redirect('/admin/postcards');
        }

        return Response::html($this->templates->render('pages/admin-art-picker', [
            'heading' => 'A picture for "' . $design['name'] . '"',
            'wantedKind' => ArtKind::Card,
            'choices' => $this->art->ofKind(ArtKind::Card),
            'currentArtId' => $design['art_id'],
            'action' => '/admin/postcards/' . $design['id'] . '/picture',
            'backTo' => '/admin/postcards/' . $design['id'],
        ]));
    }

    public function choosePicture(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) =>
            $this->editor->choosePicture($actor, $id, (int) $request->input('art_id', '0'), $request->clientIp()));
    }

    /**
     * User, CSRF, rate limit, the one action, flash. A new card opens in its
     * editor; a deleted one goes back to the list; everything else stays on
     * the card's own page.
     *
     * @param callable(User): ContentEditResult $action
     */
    private function handle(Request $request, ?int $id, callable $action, bool $isDelete = false): Response
    {
        $backTo = $id === null ? '/admin/postcards' : '/admin/postcards/' . $id;
        $actor = $this->users->findById((int) $this->session->get('user_id'));
        if ($actor === null) {
            return Response::redirect('/login');
        }
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }
        $bucket = 'user:' . $actor->id;
        $limit = $this->saveRateLimit;
        if (!$this->rateLimiter->isAllowed('content_save', $bucket, $limit['max_attempts'], $limit['window_seconds'])) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('content_save', $bucket);

        $result = $action($actor);
        $this->session->flash($result->message);

        if ($result->success && $isDelete) {
            return Response::redirect('/admin/postcards');
        }
        if ($id === null && $result->success && $result->contentId !== null) {
            return Response::redirect('/admin/postcards/' . $result->contentId);
        }

        return Response::redirect($backTo);
    }
}
