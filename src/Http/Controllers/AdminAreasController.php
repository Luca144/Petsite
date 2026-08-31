<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Exploration\AreaEditorService;
use Felkyo\Exploration\AreaRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The area list, and the door to a new place to explore.
 *
 * @package Felkyo\Http\Controllers
 *
 * One card per area, drafts saying what they still need. Web glue only;
 * the rules live in AreaEditorService.
 */
final class AdminAreasController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private AreaRepository $areas,
        private AreaEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        $cards = [];
        foreach ($this->areas->all() as $area) {
            $cards[] = [
                'area' => $area,
                'missing' => $area->isPublished() ? [] : $this->editor->missingForPublish($area),
            ];
        }

        return Response::html($this->templates->render('pages/admin-areas', [
            'cards' => $cards,
        ]));
    }

    /**
     * Start a new area: a name in, its editor page out.
     */
    public function create(Request $request): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/areas');
        }

        if (!$this->rateLimiter->isAllowed(
            'content_save',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect('/admin/areas');
        }
        $this->rateLimiter->record('content_save', 'user:' . $actor->id);

        $result = $this->editor->create($actor, $request->input('name'), $request->clientIp());
        $this->session->flash($result->message);

        return Response::redirect(
            $result->success && $result->contentId !== null
                ? '/admin/areas/' . $result->contentId
                : '/admin/areas'
        );
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The area list was reached without a logged-in user.');
        }

        return $user;
    }
}
