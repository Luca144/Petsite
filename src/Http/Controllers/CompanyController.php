<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Social\CompanyRepository;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Friends as bookmarks, and blocking (M6.1).
 *
 *   GET  /friends                         your bookmarks, and who you've blocked
 *   POST /player/{username}/friend        bookmark them
 *   POST /player/{username}/unfriend
 *   POST /player/{username}/block         stops their cards and bottles reaching you
 *   POST /player/{username}/unblock
 *
 * @package Felkyo\Http\Controllers
 *
 * Who may? Any logged-in player, about anyone but themselves — and it only
 * ever writes rows where user_id is the viewer, so nobody can change
 * someone else's list. Bookmarking needs no consent because it grants
 * nothing. Rate-limited per account ('company').
 */
final class CompanyController
{
    private const ACTIONS = ['friend', 'unfriend', 'block', 'unblock'];

    /** @param array{max_attempts: int, window_seconds: int} $rateLimit */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private CompanyRepository $company,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        return Response::html($this->templates->render('pages/friends', [
            'friends' => $this->company->friendsOf($userId),
            'blocked' => $this->company->blockedBy($userId),
        ]));
    }

    /** One handler for all four actions; the route says which. */
    public function act(Request $request, array $parameters, string $action): Response
    {
        $username = (string) ($parameters['username'] ?? '');
        $backTo = $request->input('from') === 'friends' ? '/friends' : '/player/' . rawurlencode($username);

        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }
        if (!in_array($action, self::ACTIONS, true) || !$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }
        $other = $this->users->findByUsername($username);
        if ($other === null || $other->id === $userId) {
            return Response::redirect('/friends');
        }
        if (!$this->rateLimiter->isAllowed('company', 'user:' . $userId, $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('That\'s a lot of changes — give it a little while.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('company', 'user:' . $userId);

        match ($action) {
            'friend' => $this->company->addFriend($userId, $other->id),
            'unfriend' => $this->company->removeFriend($userId, $other->id),
            'block' => $this->company->block($userId, $other->id),
            'unblock' => $this->company->unblock($userId, $other->id),
        };
        $this->session->flash(match ($action) {
            'friend' => sprintf('%s is on your friends list — find them any time under "friends".', $other->username),
            'unfriend' => sprintf('%s is off your friends list.', $other->username),
            'block' => sprintf('Blocked. Cards and bottles from %s won\'t reach you any more. They aren\'t told.', $other->username),
            'unblock' => sprintf('%s is unblocked.', $other->username),
        });

        return Response::redirect($backTo);
    }
}
