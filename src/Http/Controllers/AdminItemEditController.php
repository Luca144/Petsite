<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentVersionRepository;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
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
 * One item, everything about it, edited in place.
 *
 * @package Felkyo\Http\Controllers
 *
 * The same shape as the species editor's controller: every POST is the same
 * three checks (user, CSRF, rate limit) via handle(), then one service
 * call, then flash-and-redirect. The icon picker is two more actions using
 * the shared art-picker template.
 */
final class AdminItemEditController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ItemRepository $items,
        private ContentVersionRepository $versions,
        private ItemEditorService $editor,
        private ArtRepository $art,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    /** @param array{id: string} $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $item = $this->items->findById((int) ($parameters['id'] ?? 0));
        if ($item === null) {
            $this->session->flash('That item is gone.');

            return Response::redirect('/admin/items');
        }

        return Response::html($this->templates->render('pages/admin-item-edit', [
            'item' => $item,
            'categories' => $this->items->allCategories(),
            'missing' => $item->isPublished() ? [] : $this->editor->missingForPublish($item),
            'references' => $this->editor->referencesFor($item),
            'versions' => $this->versions->listFor('item', $item->id),
        ]));
    }

    /** @param array{id: string} $parameters */
    public function saveWords(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->saveWords($actor, $id, [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ], $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function saveNumbers(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->saveNumbers($actor, $id, [
                'price' => (int) $request->input('price', '0'),
                'sell_value' => (int) $request->input('sell_value', '0'),
                'happiness_bonus' => (int) $request->input('happiness_bonus', '0'),
                'energy_bonus' => (int) $request->input('energy_bonus', '0'),
                'category_id' => (int) $request->input('category_id', '0'),
            ], $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function publish(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->publish($actor, $id, $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function unpublish(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->unpublish($actor, $id, $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function revert(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->revert($actor, $id, (int) $request->input('version_id', '0'), $request->clientIp());
        });
    }

    /**
     * The icon picker — the shared art-picker page, filtered to Icons.
     *
     * @param array{id: string} $parameters
     */
    public function pickIcon(Request $request, array $parameters): Response
    {
        $item = $this->items->findById((int) ($parameters['id'] ?? 0));
        if ($item === null) {
            $this->session->flash('That item is gone.');

            return Response::redirect('/admin/items');
        }

        $choices = array_values(array_filter(
            $this->art->all(),
            static fn ($picture): bool => $picture->kind === ArtKind::Icon
        ));

        return Response::html($this->templates->render('pages/admin-art-picker', [
            'heading' => sprintf('An icon for %s', $item->name),
            'wantedKind' => ArtKind::Icon,
            'choices' => $choices,
            'currentArtId' => $item->iconArtId,
            'action' => '/admin/items/' . $item->id . '/icon',
            'backTo' => '/admin/items/' . $item->id,
        ]));
    }

    /** @param array{id: string} $parameters */
    public function chooseIcon(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->chooseIcon($actor, $id, (int) $request->input('art_id', '0'), $request->clientIp());
        });
    }

    /**
     * User, CSRF, rate limit, the action, flash, back to the item's page.
     */
    private function handle(Request $request, array $parameters, callable $action): Response
    {
        $actor = $this->currentUser();
        $itemId = (int) ($parameters['id'] ?? 0);
        $backTo = '/admin/items/' . $itemId;

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }

        if (!$this->rateLimiter->isAllowed(
            'content_save',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('content_save', 'user:' . $actor->id);

        $result = $action($actor, $itemId);
        $this->session->flash($result->message);

        return Response::redirect($result->contentId !== null ? '/admin/items/' . $result->contentId : '/admin/items');
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The item editor was reached without a logged-in user.');
        }

        return $user;
    }
}
