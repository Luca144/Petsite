<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ItemDisposalService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * One item a player owns: its page, and the two things they can do with it.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHO MAY DO ANY OF THIS: only the person who owns the item. That is enforced by
 * every lookup here being scoped to the logged-in player's own id, so a request
 * naming somebody else's item simply finds nothing — the same answer it would
 * give for an item that does not exist. The rules themselves live in
 * ItemDisposalService; this class handles login, CSRF, rate limiting, and turning
 * the outcome into a page.
 *
 * SELLING AND DISCARDING ARE SEPARATE ROUTES, not one route with a flag. A
 * boolean parameter deciding whether somebody gets paid or loses a thing for
 * nothing would be exactly the "boolean parameters are a smell" case in
 * CLAUDE.md section 4 — and it would put the difference between the two in a
 * value the browser sends.
 */
final class ItemController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private InventoryRepository $inventory,
        private ItemDisposalService $disposal,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    /**
     * The item's own page: what it is, what it is worth, what can be done with it.
     *
     * @param array<string, string> $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        $stack = $this->inventory->findStackForUser($userId, (int) ($parameters['id'] ?? 0));

        // Not owned, or does not exist — deliberately the same answer either way.
        // Telling somebody "that item exists but is not yours" would confirm what
        // other players own, one guessed number at a time.
        if ($stack === null) {
            $this->session->flash('You don\'t own one of those.');
            return Response::redirect('/inventory');
        }

        return Response::html($this->templates->render('pages/item', [
            'stack' => $stack,
            'flash' => $this->session->takeFlash(),
        ]));
    }

    public function sell(Request $request, array $parameters): Response
    {
        return $this->act($request, $parameters, 'sell');
    }

    public function discard(Request $request, array $parameters): Response
    {
        return $this->act($request, $parameters, 'discard');
    }

    /**
     * The checks both actions share: logged in, a valid token, and not too fast.
     *
     * The two actions differ only in which service method runs, so the guards are
     * written once. $action is chosen by us from the two methods above — it never
     * comes from the browser, which is what keeps this from being the boolean
     * parameter the docblock warns about.
     *
     * @param array<string, string> $parameters
     */
    private function act(Request $request, array $parameters, string $action): Response
    {
        $itemId = (int) ($parameters['id'] ?? 0);
        $itemPath = '/inventory/' . $itemId;

        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($itemPath);
        }

        // Selling is a state-changing action that grants currency, so it is rate
        // limited like every other one (CLAUDE.md section 6). This also blunts the
        // crudest way to attempt a double payment: firing the same request over
        // and over. The real protection is in the database, but making the attempt
        // slow as well costs nothing.
        if (!$this->rateLimiter->isAllowed('item-disposal', $request->clientIp(), $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('That is a little too fast — take a breath and try again.');
            return Response::redirect($itemPath);
        }
        $this->rateLimiter->record('item-disposal', $request->clientIp());

        $result = $action === 'sell'
            ? $this->disposal->sell($userId, $itemId)
            : $this->disposal->discard($userId, $itemId);

        $this->session->flash($result->message());

        // After letting go of the last one there is no item page left to return
        // to, so we go back to the inventory rather than to a page that would
        // turn them straight round again with "you don't own one of those".
        return Response::redirect(
            $this->inventory->findStackForUser($userId, $itemId) === null ? '/inventory' : $itemPath
        );
    }
}
