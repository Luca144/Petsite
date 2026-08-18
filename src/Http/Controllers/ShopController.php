<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreaturePurchaseService;
use Felkyo\Economy\ItemFinder;
use Felkyo\Economy\PurchaseService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * Shows the shop and handles buying from it.
 *
 * @package Felkyo\Http\Controllers
 *
 * show() lists the shop's stock. buy() is a state-changing POST (login + CSRF +
 * a light IP rate limit) that runs a purchase and returns to the shop with a
 * flash message. Which shop this is comes from config (its slug), so pointing at
 * a different shop later is a config change.
 */
final class ShopController
{
    /**
     * @param array{slug: string, rate_limit: array{max_attempts: int, window_seconds: int}, search_shown_from: int} $config
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private ShopRepository $shops,
        private PurchaseService $purchase,
        // Buying a creature is a different transaction from buying a treat — it
        // creates a living thing rather than adding to a pile — so it has its own
        // service. They share this page and nothing else.
        private CreaturePurchaseService $creaturePurchase,
        private RateLimiter $rateLimiter,
        private ItemFinder $finder,
        private array $config,
    ) {
    }

    public function show(Request $request): Response
    {
        if (!is_int($this->session->get('user_id'))) {
            return Response::redirect('/login');
        }

        $shop = $this->shops->findBySlug($this->config['slug']);
        if ($shop === null) {
            // The shop was never seeded — a setup error, shown plainly.
            return Response::html('The shop is not available right now.', 500);
        }

        // The whole stock, then what the finder row narrows it to. The stock is
        // public catalogue data, so the only validation the filters need is the
        // finder's own (length-capped search, category must actually exist).
        $allItems = $this->shops->findItems($shop->id);
        $categories = $this->finder->categoriesOfItems($allItems);
        $categorySlug = $this->finder->validCategorySlug($request->query('category'), $categories);
        $searchText = $this->finder->cleanSearchText($request->query('q'));
        $items = $this->finder->filterItems($allItems, $categorySlug, $searchText);

        return Response::html($this->templates->render('pages/shop', [
            'shop' => $shop,
            'items' => $items,
            // The creatures sit outside the finder, so they are passed separately
            // and are never filtered by it (a creature is not a thing on a shelf).
            'creaturesForSale' => $this->creaturePurchase->forSale(),
            'isFiltered' => $categorySlug !== '' || $searchText !== '',
            'finder' => [
                'action' => '/shop',
                'categories' => $categories,
                'activeSlug' => $categorySlug,
                'searchText' => $searchText,
                'totalCount' => count($allItems),
                'shownCount' => count($items),
                'searchShownFrom' => $this->config['search_shown_from'],
                'thingsWord' => 'items',
                'emptyLine' => 'Ruinily doesn’t have anything like that on the shelves.',
            ],
        ]));
    }

    public function buy(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect('/shop');
        }

        $limit = $this->config['rate_limit'];
        if (!$this->rateLimiter->isAllowed('purchase', $request->clientIp(), $limit['max_attempts'], $limit['window_seconds'])) {
            $this->session->flash('You are buying a little too fast — please slow down.');
            return Response::redirect('/shop');
        }
        $this->rateLimiter->record('purchase', $request->clientIp());

        $shop = $this->shops->findBySlug($this->config['slug']);
        if ($shop === null) {
            return Response::redirect('/shop');
        }

        $itemId = (int) $request->input('item_id');
        $result = $this->purchase->buy($userId, $shop->id, $itemId);
        $this->session->flash($result->message());

        return Response::redirect('/shop');
    }

    /**
     * Buy a creature. Its own action rather than a flag on buy(), because "get
     * paid a pile of treats" and "take a living thing home" are different enough
     * that the difference should never be a value the browser sends.
     */
    public function buyCreature(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect('/shop');
        }

        // The same limit buying anything else uses. The real gate is the price.
        $limit = $this->config['rate_limit'];
        if (!$this->rateLimiter->isAllowed('purchase', $request->clientIp(), $limit['max_attempts'], $limit['window_seconds'])) {
            $this->session->flash('You are buying a little too fast — please slow down.');
            return Response::redirect('/shop');
        }
        $this->rateLimiter->record('purchase', $request->clientIp());

        $result = $this->creaturePurchase->buy($userId, (int) $request->input('species_id'));
        $this->session->flash($result->message());

        // Go straight to the new arrival. Meeting it is the whole point of having
        // bought it, and leaving somebody to find it in a list afterwards would
        // waste the one moment the purchase was for.
        $creature = $result->creature();
        if ($creature !== null) {
            return Response::redirect('/creature/' . $creature->id);
        }

        return Response::redirect('/shop');
    }
}
