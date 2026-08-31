<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentVersionRepository;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Auth\Session;
use Felkyo\Economy\ItemRepository;
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
 * One shop, everything about it, edited in place — words, keeper, pictures
 * and the shelves.
 *
 * @package Felkyo\Http\Controllers
 *
 * The one-sitting shop editor the Lead Developer asked for: set the
 * background, the keeper and their lines, stock the shelves with their
 * seasons, and publish. The same handle() shape as every editor; the
 * picture picker serves both slots through the shared art-picker template.
 */
final class AdminShopEditController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ShopRepository $shops,
        private ItemRepository $items,
        private ContentVersionRepository $versions,
        private ShopEditorService $editor,
        private ArtRepository $art,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    /** @param array{id: string} $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $shop = $this->shops->findById((int) ($parameters['id'] ?? 0));
        if ($shop === null) {
            $this->session->flash('That shop is gone.');

            return Response::redirect('/admin/shops');
        }

        return Response::html($this->templates->render('pages/admin-shop-edit', [
            'shop' => $shop,
            'listings' => $this->shops->listingsFor($shop->id),
            // What can go on the shelf: published items only — the same rule
            // the service enforces, shown rather than discovered.
            'stockableItems' => $this->items->allPublished(),
            'versions' => $this->versions->listFor('shop', $shop->id),
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
    public function saveKeeper(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->saveKeeper($actor, $id, [
                'name' => $request->input('keeper_name'),
                'greeting' => $request->input('keeper_greeting'),
                'refusal' => $request->input('keeper_refusal'),
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
     * Put an item on the shelf (or change its season).
     *
     * @param array{id: string} $parameters
     */
    public function putListing(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->putListing($actor, $id, [
                'item_id' => (int) $request->input('item_id', '0'),
                'available_from' => $request->input('available_from'),
                'available_to' => $request->input('available_to'),
            ], $request->clientIp());
        });
    }

    /**
     * Take an item off the shelf.
     *
     * @param array{id: string, itemId: string} $parameters
     */
    public function removeListing(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request, $parameters) {
            return $this->editor->removeListing($actor, $id, (int) ($parameters['itemId'] ?? 0), $request->clientIp());
        });
    }

    /**
     * The picture picker for one of the shop's two slots — the shared
     * art-picker page, filtered to the slot's kind.
     *
     * @param array{id: string, slot: string} $parameters
     */
    public function pickPicture(Request $request, array $parameters): Response
    {
        $shop = $this->shops->findById((int) ($parameters['id'] ?? 0));
        $slot = (string) ($parameters['slot'] ?? '');
        $wantedKind = match ($slot) {
            'background' => ArtKind::Background,
            'portrait' => ArtKind::Portrait,
            default => null,
        };

        if ($shop === null || $wantedKind === null) {
            $this->session->flash('That picture slot doesn\'t exist.');

            return Response::redirect('/admin/shops');
        }

        $choices = array_values(array_filter(
            $this->art->all(),
            static fn ($picture): bool => $picture->kind === $wantedKind
        ));

        return Response::html($this->templates->render('pages/admin-art-picker', [
            'heading' => sprintf(
                '%s for %s',
                $slot === 'background' ? 'A background' : 'A portrait',
                $shop->name
            ),
            'wantedKind' => $wantedKind,
            'choices' => $choices,
            'currentArtId' => $slot === 'background' ? $shop->backgroundArtId : $shop->portraitArtId,
            'action' => '/admin/shops/' . $shop->id . '/picture/' . $slot,
            'backTo' => '/admin/shops/' . $shop->id,
        ]));
    }

    /** @param array{id: string, slot: string} $parameters */
    public function choosePicture(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request, $parameters) {
            return $this->editor->choosePicture(
                $actor, $id, (string) ($parameters['slot'] ?? ''),
                (int) $request->input('art_id', '0'), $request->clientIp()
            );
        });
    }

    /**
     * User, CSRF, rate limit, the action, flash, back to the shop's page.
     */
    private function handle(Request $request, array $parameters, callable $action): Response
    {
        $actor = $this->currentUser();
        $shopId = (int) ($parameters['id'] ?? 0);
        $backTo = '/admin/shops/' . $shopId;

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

        $result = $action($actor, $shopId);
        $this->session->flash($result->message);

        // Back to the shop's own page either way — a refusal's reasons
        // belong next to the form they refuse, not on the list. (Refused
        // results carry no contentId, so keying the redirect on it sent
        // refusals to the list; the area editor's smoke test caught it.)
        return Response::redirect($backTo);
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The shop editor was reached without a logged-in user.');
        }

        return $user;
    }
}
