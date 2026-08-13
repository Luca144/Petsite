<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
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
            'isFiltered' => $categorySlug !== '' || $searchText !== '',
            'flash' => $this->session->takeFlash(),
            'finder' => [
                'action' => '/shop',
                'categories' => $categories,
                'activeSlug' => $categorySlug,
                'searchText' => $searchText,
                'totalCount' => count($allItems),
                'shownCount' => count($items),
                'searchShownFrom' => $this->config['search_shown_from'],
                'thingsWord' => 'items',
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
}
