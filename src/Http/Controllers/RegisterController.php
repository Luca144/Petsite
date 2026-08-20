<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\RegistrationService;
use Felkyo\Auth\Session;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * Handles the "create an account" page and form.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHAT A CONTROLLER DOES: it is the web-facing glue. It reads the request, calls
 * the right service to do the real work, and returns a Response (a page to show,
 * or a redirect). It contains NO database code and NO business rules — those live
 * in repositories and services. This boundary is deliberate (CLAUDE.md section 5).
 *
 * This controller has two actions: show() displays the empty form, and submit()
 * processes it. Both are switched off when registration is closed (see below).
 */
final class RegisterController
{
    /**
     * @param bool $registrationOpen Whether public sign-ups are allowed at all.
     *                               False on the deployed demo, which runs only on
     *                               seeded accounts (see config app.registration_open).
     */
    public function __construct(
        private Engine $templates,
        private Csrf $csrf,
        private Session $session,
        private RegistrationService $registration,
        private StarterCreatureService $starterCreatures,
        private RateLimiter $rateLimiter,
        private array $securityConfig,
        private bool $registrationOpen,
    ) {
    }

    /**
     * Show the registration form. If the visitor is already logged in, there is
     * nothing to register, so send them to the home page instead.
     */
    public function show(Request $request): Response
    {
        if (!$this->registrationOpen) {
            return $this->registrationClosedPage();
        }

        if ($this->session->has('user_id')) {
            return Response::redirect('/');
        }

        return $this->renderForm();
    }

    /**
     * Process a submitted registration form.
     */
    public function submit(Request $request): Response
    {
        // The closed check comes FIRST, before anything else is read or done. The
        // form is hidden when registration is closed, but hiding a form does not
        // stop anyone posting to the address directly — so the refusal has to live
        // here, on the action itself, not only in the page that shows the form.
        if (!$this->registrationOpen) {
            return $this->registrationClosedPage();
        }

        if ($this->session->has('user_id')) {
            return Response::redirect('/');
        }

        // 1. Confirm the form really came from our own page (CSRF protection).
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return $this->renderForm(
                ['Your session expired. Please try submitting the form again.'],
                $request,
                400
            );
        }

        // 2. Enforce the anti-spam limit on how many accounts one IP can create.
        $limit = $this->securityConfig['rate_limit_register'];
        if (!$this->rateLimiter->isAllowed('register', $request->clientIp(), $limit['max_attempts'], $limit['window_seconds'])) {
            return $this->renderForm(
                ['Too many sign-ups from here recently. Please wait a while and try again.'],
                $request,
                429
            );
        }

        // 3. Do the actual registration (validate, check uniqueness, hash, save).
        $result = $this->registration->register(
            $request->input('username'),
            $request->input('email'),
            $request->input('password')
        );

        if (!$result->isSuccessful()) {
            // Invalid input or a taken name/email — show the reasons and let them
            // fix it. This does NOT count against the sign-up limit.
            return $this->renderForm($result->errors(), $request, 422);
        }

        // 4. Success: give the new player their starter creature, count the
        // sign-up against the limit, log them in (fresh session id AND fresh
        // CSRF token — a token from before login must not survive it), and
        // go home.
        $this->starterCreatures->grantStarterTo($result->user());
        $this->rateLimiter->record('register', $request->clientIp());
        $this->session->regenerateId();
        $this->csrf->rotate();
        $this->session->set('user_id', $result->user()->id);

        return Response::redirect('/');
    }

    /**
     * The page shown when sign-ups are closed. Both show() and submit() return
     * this same page, so a visitor who bookmarked the form and a script posting
     * straight to the address get the identical, clear answer — and neither can
     * create an account.
     *
     * 403 ("Forbidden") is the honest status: the page exists, but this action is
     * not allowed right now.
     */
    private function registrationClosedPage(): Response
    {
        return Response::html($this->templates->render('pages/registration-closed'), 403);
    }

    /**
     * Render the registration page, optionally with error messages and the
     * values the person already typed (so they are not lost on an error).
     *
     * @param string[] $errors
     */
    private function renderForm(array $errors = [], ?Request $request = null, int $statusCode = 200): Response
    {
        $html = $this->templates->render('pages/register', [
            'errors' => $errors,
            // Keep the username and email in the form after an error, but never
            // the password (it is not echoed back for safety).
            'username' => $request?->input('username') ?? '',
            'email' => $request?->input('email') ?? '',
        ]);

        return Response::html($html, $statusCode);
    }
}
