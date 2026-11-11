<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Look\CustomCssRepository;
use Felkyo\Look\CustomCssRules;
use Felkyo\Look\SiteLook;
use Felkyo\Look\SiteLookService;
use Felkyo\Security\RateLimiter;
use Felkyo\Themes\ThemeRepository;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The panel's "Site look" screens (artist role; owner passes).
 *
 *   GET  /admin/look                      default theme, section order, announcement
 *   POST /admin/look/default-theme
 *   POST /admin/look/sections             move one section up or down
 *   POST /admin/look/sections/reset
 *   POST /admin/look/announcement
 *   GET  /admin/look/css                  the fine-tuning CSS box and its history
 *   POST /admin/look/css/preview          try it out — only you see it
 *   POST /admin/look/css/stop-preview
 *   POST /admin/look/css/publish
 *   POST /admin/look/css/restore/{id}
 *
 * @package Felkyo\Http\Controllers
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md §7):
 * 1. Who may? The artist role, by the AdminGate on every route. Each POST
 *    also checks CSRF and the content-save rate limit.
 * 2. Worst case? A bad CSS rule breaking the site for everyone — answered by
 *    preview-first, full history and one-click restore; hostile CSS — refused
 *    by CustomCssRules, checked on save AND on print.
 * 3. Tests: the smoke test publishes, previews and restores real CSS.
 */
final class AdminLookController
{
    /** Where a CSS preview waits, in the previewer's own session only. */
    public const PREVIEW_KEY = 'look_css_preview';

    /** @param array{max_attempts: int, window_seconds: int} $saveRateLimit */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private SiteLook $look,
        private SiteLookService $service,
        private ThemeRepository $themes,
        private CustomCssRepository $cssHistory,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-look', [
            'look' => $this->look,
            'themes' => $this->themes->published(),
        ]));
    }

    public function defaultTheme(Request $request): Response
    {
        $raw = $request->input('theme_id');

        return $this->handle($request, '/admin/look', fn (User $actor) => $this->service->setDefaultTheme(
            $actor, $raw === '' ? null : (int) $raw, $request->clientIp()
        ));
    }

    public function moveSection(Request $request): Response
    {
        return $this->handle($request, '/admin/look#order', fn (User $actor) => $this->service->moveSection(
            $actor, $this->look, $request->input('page'), $request->input('section'),
            $request->input('direction') === 'up' ? -1 : 1, $request->clientIp()
        ));
    }

    public function resetSections(Request $request): Response
    {
        return $this->handle($request, '/admin/look#order', fn (User $actor) => $this->service->resetOrder(
            $actor, $request->input('page'), $request->clientIp()
        ));
    }

    public function announcement(Request $request): Response
    {
        return $this->handle($request, '/admin/look', fn (User $actor) => $this->service->setAnnouncement(
            $actor, $request->input('announcement'), $request->clientIp()
        ));
    }

    public function css(Request $request): Response
    {
        $preview = $this->session->get(self::PREVIEW_KEY);

        return Response::html($this->templates->render('pages/admin-look-css', [
            'css' => is_string($preview) ? $preview : $this->look->customCss,
            'isPreviewing' => is_string($preview),
            'history' => $this->cssHistory->history(30),
            'maxLength' => CustomCssRules::MAX_LENGTH,
        ]));
    }

    /** Keep the draft in this person's session; the layout prints it for them alone. */
    public function preview(Request $request): Response
    {
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/look/css');
        }
        $css = str_replace("\r\n", "\n", $request->input('css'));
        $problem = CustomCssRules::problemWith($css);
        if ($problem !== null) {
            $this->session->flash('Can\'t preview that: ' . $problem);

            return Response::redirect('/admin/look/css');
        }
        $this->session->set(self::PREVIEW_KEY, $css);
        $this->session->flash('Previewing — only you can see it. Look around the site, then come back to publish or stop.');

        return Response::redirect('/admin/look/css');
    }

    public function stopPreview(Request $request): Response
    {
        if ($this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->remove(self::PREVIEW_KEY);
            $this->session->flash('Stopped previewing. You\'re seeing what everyone sees.');
        }

        return Response::redirect('/admin/look/css');
    }

    public function publishCss(Request $request): Response
    {
        return $this->handle($request, '/admin/look/css', function (User $actor) use ($request): ContentEditResult {
            $result = $this->service->publishCss($actor, $request->input('css'), $request->input('note'), $request->clientIp());
            if ($result->success) {
                $this->session->remove(self::PREVIEW_KEY);
            }

            return $result;
        });
    }

    public function restoreCss(Request $request, array $parameters): Response
    {
        return $this->handle($request, '/admin/look/css', function (User $actor) use ($request, $parameters): ContentEditResult {
            $this->session->remove(self::PREVIEW_KEY);

            return $this->service->restoreCss($actor, (int) ($parameters['id'] ?? 0), $request->clientIp());
        });
    }

    /** @param callable(User): ContentEditResult $action */
    private function handle(Request $request, string $backTo, callable $action): Response
    {
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

        $this->session->flash($action($actor)->message);

        return Response::redirect($backTo);
    }
}
