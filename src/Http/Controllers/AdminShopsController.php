<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Economy\ShopEditorService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The shop list, and the door to a new shop.
 *
 * @package Felkyo\Http\Controllers
 *
 * One shop today, more in M9 — built here, complete, the day M9 routes to
 * them. Web glue only; the rules live in ShopEditorService.
 */
final class AdminShopsController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ShopRepository $shops,
        private ShopEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-shops', [
            'shops' => $this->shops->allShops(),
        ]));
    }

    /**
     * Start a new shop: a name in, its editor page out.
     */
    public function create(Request $request): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/shops');
        }

        if (!$this->rateLimiter->isAllowed(
            'content_save',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect('/admin/shops');
        }
        $this->rateLimiter->record('content_save', 'user:' . $actor->id);

        $result = $this->editor->create($actor, $request->input('name'), $request->clientIp());
        $this->session->flash($result->message);

        return Response::redirect(
            $result->success && $result->contentId !== null
                ? '/admin/shops/' . $result->contentId
                : '/admin/shops'
        );
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The shop list was reached without a logged-in user.');
        }

        return $user;
    }
}
