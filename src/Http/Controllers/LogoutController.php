<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;

/**
 * Handles logging out.
 *
 * @package Felkyo\Http\Controllers
 *
 * Logging out CHANGES state (it ends the session), so it is a POST with a CSRF
 * token — never a plain link. That stops another site from logging you out
 * without your intent, and follows the rule that state-changing actions are POSTs.
 */
final class LogoutController
{
    public function __construct(
        private Session $session,
        private Csrf $csrf,
    ) {
    }

    /**
     * Log the visitor out and send them to the home page. If the CSRF token is
     * missing or wrong we still just send them home (no harm done, nothing changed).
     */
    public function submit(Request $request): Response
    {
        if ($this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->logout();
        }

        return Response::redirect('/');
    }
}
