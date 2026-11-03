<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtRepository;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Library\ArticleEditorService;
use Felkyo\Library\ArticleRepository;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The panel's Gazette desk (M5.4): write, publish and illustrate articles.
 *
 * @package Felkyo\Http\Controllers
 *
 *   GET  /admin/gazette                    every article, drafts included
 *   POST /admin/gazette/create             start a draft from a headline
 *   GET  /admin/gazette/{id}               edit one article
 *   POST /admin/gazette/{id}/words|publish|unpublish|delete
 *   GET  /admin/gazette/{id}/picture       the picture picker
 *   POST /admin/gazette/{id}/picture
 *
 * Behind AdminGate (artist role). Every POST runs handle(): user, CSRF,
 * the shared content-save rate limit, one service call.
 */
final class AdminGazetteController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ArticleRepository $articles,
        private ArtRepository $art,
        private ArticleEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-gazette', [
            'articles' => $this->articles->all(),
        ]));
    }

    /** @param array{id: string} $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $article = $this->articles->findById((int) ($parameters['id'] ?? 0));
        if ($article === null) {
            $this->session->flash('That article is gone.');

            return Response::redirect('/admin/gazette');
        }

        return Response::html($this->templates->render('pages/admin-gazette-edit', ['article' => $article]));
    }

    public function create(Request $request): Response
    {
        return $this->handle($request, null, fn (User $actor) =>
            $this->editor->create($actor, $request->input('title'), $request->clientIp()));
    }

    /** @param array{id: string} $parameters */
    public function saveWords(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) => $this->editor->saveWords(
            $actor, $id, $request->input('title'), $request->input('summary'), $request->input('body'), $request->clientIp()
        ));
    }

    /** @param array{id: string} $parameters */
    public function publish(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) =>
            $this->editor->setPublished($actor, $id, true, $request->clientIp()));
    }

    /** @param array{id: string} $parameters */
    public function unpublish(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) =>
            $this->editor->setPublished($actor, $id, false, $request->clientIp()));
    }

    /** Deleting goes back to the list — the article's own page is gone. */
    public function delete(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, null, fn (User $actor) =>
            $this->editor->delete($actor, $id, $request->clientIp()));
    }

    /** @param array{id: string} $parameters */
    public function pickPicture(Request $request, array $parameters): Response
    {
        $article = $this->articles->findById((int) ($parameters['id'] ?? 0));
        if ($article === null) {
            return Response::redirect('/admin/gazette');
        }

        $allowed = $this->editor->allowedArtKinds();

        return Response::html($this->templates->render('pages/admin-art-picker', [
            'heading' => 'A picture for "' . $article->title . '"',
            'wantedKind' => $allowed[0],
            'choices' => array_values(array_filter(
                $this->art->all(),
                static fn ($picture): bool => in_array($picture->kind, $allowed, true)
            )),
            'currentArtId' => $article->artId,
            'action' => '/admin/gazette/' . $article->id . '/picture',
            'backTo' => '/admin/gazette/' . $article->id,
        ]));
    }

    /** @param array{id: string} $parameters */
    public function choosePicture(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $id, fn (User $actor) =>
            $this->editor->choosePicture($actor, $id, (int) $request->input('art_id', '0'), $request->clientIp()));
    }

    /**
     * User, CSRF, rate limit, the one action, flash, then back to the
     * article (or to the list when there is no article page to return to:
     * creating sends you to the new draft, deleting to the list).
     *
     * @param callable(User): ContentEditResult $action
     */
    private function handle(Request $request, ?int $articleId, callable $action): Response
    {
        $backTo = $articleId === null ? '/admin/gazette' : '/admin/gazette/' . $articleId;
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

        // A freshly created draft opens straight away, ready to write.
        if ($articleId === null && $result->success && $result->contentId !== null) {
            return Response::redirect('/admin/gazette/' . $result->contentId);
        }

        return Response::redirect($backTo);
    }
}
