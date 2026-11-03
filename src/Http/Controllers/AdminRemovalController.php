<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Admin\ContentRemovalService;
use Felkyo\Auth\Session;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\ItemRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The "delete for good" pages for a species or an item.
 *
 *   GET  /admin/creatures/{id}/delete   what deleting would touch, and the form
 *   POST /admin/creatures/{id}/delete
 *   GET  /admin/items/{id}/delete
 *   POST /admin/items/{id}/delete
 *
 * @package Felkyo\Http\Controllers
 *
 * Behind AdminGate (artist role). The rules (draft only, typed name, nobody
 * loses out) live in ContentRemovalService; this page explains them first,
 * in plain words, before anything can be pressed.
 */
final class AdminRemovalController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private SpeciesRepository $species,
        private ItemRepository $items,
        private ContentRemovalService $removal,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function confirmSpecies(Request $request, array $parameters): Response
    {
        $species = $this->species->findById((int) ($parameters['id'] ?? 0));
        if ($species === null) {
            return Response::redirect('/admin/creatures');
        }

        return Response::html($this->templates->render('pages/admin-delete', [
            'kind' => 'species',
            'name' => $species->name,
            'isDraft' => !$species->isPublished(),
            'backTo' => '/admin/creatures/' . $species->id,
            'action' => '/admin/creatures/' . $species->id . '/delete',
            'affected' => $this->removal->creatureCount($species->id),
            'replacements' => array_values(array_filter(
                $this->species->findPublished(),
                static fn ($other): bool => $other->id !== $species->id
            )),
        ]));
    }

    public function deleteSpecies(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, '/admin/creatures/' . $id . '/delete', '/admin/creatures', fn (User $actor) =>
            $this->removal->deleteSpecies($actor, $id, $request->input('confirm_name'), (int) $request->input('replacement_id', '0'), $request->clientIp()));
    }

    public function confirmItem(Request $request, array $parameters): Response
    {
        $item = $this->items->findById((int) ($parameters['id'] ?? 0));
        if ($item === null) {
            return Response::redirect('/admin/items');
        }

        return Response::html($this->templates->render('pages/admin-delete', [
            'kind' => 'item',
            'name' => $item->name,
            'isDraft' => !$item->isPublished(),
            'backTo' => '/admin/items/' . $item->id,
            'action' => '/admin/items/' . $item->id . '/delete',
            'affected' => $this->removal->ownedCount($item->id),
            'refundEach' => max($item->price, $item->sellValue),
            'replacements' => [],
        ]));
    }

    public function deleteItem(Request $request, array $parameters): Response
    {
        $id = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, '/admin/items/' . $id . '/delete', '/admin/items', fn (User $actor) =>
            $this->removal->deleteItem($actor, $id, $request->input('confirm_name'), $request->clientIp()));
    }

    /**
     * User, CSRF, rate limit, the action. A refusal goes back to the
     * confirmation page (its reason belongs next to the form); success goes
     * to the list, since the thing's own page no longer exists.
     *
     * @param callable(User): ContentEditResult $action
     */
    private function handle(Request $request, string $backTo, string $onSuccess, callable $action): Response
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

        $result = $action($actor);
        $this->session->flash($result->message);

        return Response::redirect($result->success ? $onSuccess : $backTo);
    }
}
