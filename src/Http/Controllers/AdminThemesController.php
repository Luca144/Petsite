<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Themes\ThemeEditorService;
use Felkyo\Themes\ThemeRepository;
use Felkyo\Themes\ThemeTokens;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The panel's theme editor (M4.1, M4.2).
 *
 *   GET  /admin/themes                     all themes + start a new one
 *   POST /admin/themes/create
 *   GET  /admin/themes/{id}                colour pickers, live contrast check
 *   POST /admin/themes/{id}/save|publish|unpublish|delete
 *
 * @package Felkyo\Http\Controllers
 *
 * Behind AdminGate (artist role). Every POST: user, CSRF, content-save rate
 * limit, one service call. The live contrast check in the browser is a
 * convenience; ThemeEditorService checks again on the server and is the one
 * that decides.
 */
final class AdminThemesController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ThemeRepository $themes,
        private ThemeEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-themes', ['themes' => $this->themes->all()]));
    }

    public function show(Request $request, array $parameters): Response
    {
        $theme = $this->themes->findById((int) ($parameters['id'] ?? 0));
        if ($theme === null) {
            return Response::redirect('/admin/themes');
        }

        return Response::html($this->templates->render('pages/admin-theme-edit', [
            'theme' => $theme,
            'palette' => ThemeTokens::paletteWith($theme->tokens),
            'problems' => ThemeTokens::problems($theme->tokens),
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
        $colours = [];
        foreach (array_keys(ThemeTokens::EDITABLE) as $token) {
            $colours[$token] = $request->input('colour_' . $token);
        }

        return $this->handle($request, $id, fn (User $actor) => $this->editor->save(
            $actor, $id, $request->input('name'), $colours, $request->input('easy_reading') === '1', $request->clientIp()
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

        return $this->handle($request, null, fn (User $actor) => $this->editor->delete($actor, $id, $request->clientIp()));
    }

    /** @param callable(User): ContentEditResult $action */
    private function handle(Request $request, ?int $themeId, callable $action): Response
    {
        $backTo = $themeId === null ? '/admin/themes' : '/admin/themes/' . $themeId;
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

        // A new theme opens straight into its editor.
        if ($themeId === null && $result->success && $result->contentId !== null) {
            return Response::redirect('/admin/themes/' . $result->contentId);
        }

        return Response::redirect($backTo);
    }
}
