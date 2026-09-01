<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Moderation\NoticeRepository;
use Felkyo\Security\RateLimiter;

/**
 * "I've read this" — the one thing a player can do with a notice (M2.7).
 *
 * @package Felkyo\Http\Controllers
 *
 * The one-way channel's player end. There is no reply, no form, no page:
 * one POST that marks one notice read. The SQL in NoticeRepository binds
 * the update to the session's own user, so someone else's notice id (or
 * an invented one) updates nothing — and gets the very same redirect, so
 * ids cannot be probed through this either.
 */
final class NoticeController
{
    public function __construct(
        private Session $session,
        private Csrf $csrf,
        private NoticeRepository $notices,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    /** @param array{id: string} $parameters */
    public function acknowledge(Request $request, array $parameters): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect('/');
        }

        // A gentle limit: nobody has thirty unread notices in an hour, but a
        // script probing ids would happily send thousands.
        if (!$this->rateLimiter->isAllowed(
            'notice_ack',
            'user:' . $userId,
            $this->rateLimit['max_attempts'],
            $this->rateLimit['window_seconds']
        )) {
            return Response::redirect('/');
        }
        $this->rateLimiter->record('notice_ack', 'user:' . $userId);

        $this->notices->acknowledge((int) ($parameters['id'] ?? 0), $userId);

        // Same answer whatever happened (own, read, or not theirs at all):
        // the card is gone from their next page, which is the confirmation.
        return Response::redirect('/');
    }
}
