<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\GiftService;
use Felkyo\Auth\Session;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\ItemRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The owner's gift desk: give a player gems, items or a creature.
 *
 *   GET  /admin/gifts   the form
 *   POST /admin/gifts   give it
 *
 * @package Felkyo\Http\Controllers
 *
 * OWNER ROLE ONLY (index.php wraps both routes with Role::Owner). All the
 * rules and caps are in GiftService; this class checks login, CSRF and its
 * own rate limit, then hands over.
 */
final class AdminGiftsController
{
    /** @param array{max_gems: int, max_items: int} $caps */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private SpeciesRepository $species,
        private ItemRepository $items,
        private GiftService $gifts,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
        private array $caps,
        private ?\Felkyo\Character\BadgeRepository $badges = null,
    ) {
    }

    public function show(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-gifts', [
            'species' => $this->species->findPublished(),
            'items' => $this->items->allPublished(),
            'badges' => $this->badges?->forAudience('player') ?? [],
            'caps' => $this->caps,
            // Coming from a player's account page fills the name in.
            'to' => mb_substr($request->query('to'), 0, 40),
        ]));
    }

    public function give(Request $request): Response
    {
        $actor = $this->users->findById((int) $this->session->get('user_id'));
        if ($actor === null) {
            return Response::redirect('/login');
        }
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/gifts');
        }
        if (!$this->rateLimiter->isAllowed('gift', 'user:' . $actor->id, $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('That\'s a lot of gifts for one hour — take a breather.');

            return Response::redirect('/admin/gifts');
        }
        $this->rateLimiter->record('gift', 'user:' . $actor->id);

        $result = $this->gifts->give($actor, [
            'kind' => $request->input('kind'),
            'username' => $request->input('username'),
            'amount' => (int) $request->input('amount', '1'),
            'item_id' => (int) $request->input('item_id', '0'),
            'species_id' => (int) $request->input('species_id', '0'),
            'badge_id' => (int) $request->input('badge_id', '0'),
            'creature_name' => $request->input('creature_name'),
            'reason' => $request->input('reason'),
        ], $request->clientIp());
        $this->session->flash($result->message);

        return Response::redirect('/admin/gifts');
    }
}
