<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Economy\ItemEditorService;
use Felkyo\Economy\ItemRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The item list: everything the game's economy holds, and the door to a
 * new item.
 *
 * @package Felkyo\Http\Controllers
 *
 * One card per item — its icon, name, category, price, and its status as a
 * WORD; drafts say what they still need. Clicking a card opens its editor.
 * Web glue only; the rules live in ItemEditorService.
 */
final class AdminItemsController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ItemRepository $items,
        private ItemEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        $cards = [];
        foreach ($this->items->all() as $item) {
            $cards[] = [
                'item' => $item,
                'missing' => $item->isPublished() ? [] : $this->editor->missingForPublish($item),
            ];
        }

        return Response::html($this->templates->render('pages/admin-items', [
            'cards' => $cards,
            'categories' => $this->items->allCategories(),
        ]));
    }

    /**
     * Start a new item: a name and a category in, its editor page out.
     */
    public function create(Request $request): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/items');
        }

        if (!$this->rateLimiter->isAllowed(
            'content_save',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect('/admin/items');
        }
        $this->rateLimiter->record('content_save', 'user:' . $actor->id);

        $result = $this->editor->create(
            $actor,
            $request->input('name'),
            (int) $request->input('category_id', '0'),
            $request->clientIp()
        );
        $this->session->flash($result->message);

        return Response::redirect(
            $result->success && $result->contentId !== null
                ? '/admin/items/' . $result->contentId
                : '/admin/items'
        );
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The item list was reached without a logged-in user.');
        }

        return $user;
    }
}
