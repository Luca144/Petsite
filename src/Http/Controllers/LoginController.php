<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Authenticator;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Handles the "log in" page and form.
 *
 * @package Felkyo\Http\Controllers
 *
 * Like the register controller, this is web glue only: it checks the request,
 * asks the Authenticator whether the credentials are correct, and either starts
 * a logged-in session or shows the form again with a message.
 */
final class LoginController
{
    public function __construct(
        private Engine $templates,
        private Csrf $csrf,
        private Session $session,
        private Authenticator $authenticator,
        private UserRepository $users,
        private RateLimiter $rateLimiter,
        private array $securityConfig,
    ) {
    }

    /**
     * Show the login form. Already-logged-in visitors are sent home.
     */
    public function show(Request $request): Response
    {
        if ($this->session->has('user_id')) {
            return Response::redirect('/');
        }

        return $this->renderForm();
    }

    /**
     * Process a submitted login form.
     */
    public function submit(Request $request): Response
    {
        if ($this->session->has('user_id')) {
            return Response::redirect('/');
        }

        // 1. Confirm the form came from our own page (CSRF protection).
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return $this->renderForm(
                ['Your session expired. Please try logging in again.'],
                $request,
                400
            );
        }

        // 2. Block brute-force guessing: refuse once too many recent failures.
        $limit = $this->securityConfig['rate_limit_login'];
        if (!$this->rateLimiter->isAllowed('login', $request->clientIp(), $limit['max_attempts'], $limit['window_seconds'])) {
            return $this->renderForm(
                ['Too many login attempts. Please wait a while and try again.'],
                $request,
                429
            );
        }

        // 3. Check the credentials.
        $user = $this->authenticator->attempt(
            $request->input('username'),
            $request->input('password')
        );

        if ($user === null) {
            // Wrong username OR wrong password — we show ONE message for both, so
            // we never reveal which usernames exist. Count this failed attempt.
            $this->rateLimiter->record('login', $request->clientIp());

            return $this->renderForm(
                ['The username or password is incorrect.'],
                $request,
                401
            );
        }

        // 4. Success: start a logged-in session (with a fresh id and a fresh
        // CSRF token — a token from before login must not survive it; see
        // Csrf::rotate()) and record the login time. A successful login is
        // NOT counted against the limit.
        $this->session->regenerateId();
        $this->csrf->rotate();
        $this->session->set('user_id', $user->id);
        $this->users->updateLastLogin($user->id);

        return Response::redirect('/');
    }

    /**
     * Render the login page, optionally with errors and the typed username (never
     * the password).
     *
     * @param string[] $errors
     */
    private function renderForm(array $errors = [], ?Request $request = null, int $statusCode = 200): Response
    {
        $html = $this->templates->render('pages/login', [
            'errors' => $errors,
            'username' => $request?->input('username') ?? '',
        ]);

        return Response::html($html, $statusCode);
    }
}
