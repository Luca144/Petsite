<?php

declare(strict_types=1);

namespace Felkyo\Auth;

/**
 * Manages the visitor's session (their "logged-in" state and CSRF token).
 *
 * @package Felkyo\Auth
 *
 * WHAT A SESSION IS: when someone logs in, we need to remember that across the
 * many separate requests their browser makes. PHP does this with a session: a
 * little server-side store, identified by a cookie in the browser. This class is
 * a thin, safe wrapper around it, so the rest of the app never touches the raw
 * $_SESSION array or PHP's session functions directly.
 *
 * SECURITY (CLAUDE.md section 6): start() configures the session cookie to be
 * HttpOnly (JavaScript can't read it), SameSite=Lax (limits cross-site sending),
 * and — in production — Secure (only sent over HTTPS). On login we regenerate the
 * session id, which defends against "session fixation" attacks.
 */
final class Session
{
    /**
     * @param bool $cookieSecure Whether the session cookie must be HTTPS-only.
     *                           True in production; false for local http dev, where
     *                           a Secure cookie would never be sent and login would
     *                           appear broken. Production MUST run over HTTPS.
     */
    public function __construct(private bool $cookieSecure)
    {
    }

    /**
     * Start the PHP session with safe cookie settings. Called once per web
     * request, in the front controller. If a session is already active (or we are
     * in a test that manipulates $_SESSION directly), this does nothing.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,       // the cookie lasts until the browser is closed
            'path' => '/',
            'httponly' => true,    // not readable by JavaScript
            'secure' => $this->cookieSecure,
            'samesite' => 'Lax',   // not sent on cross-site POSTs
        ]);

        session_start();
    }

    /** Read a value from the session, or return the default if it is not set. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Store a value in the session. */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Is there a value stored under this key? */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /** Remove a single value from the session. */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Give the session a brand-new id while keeping its data. We do this the
     * moment someone logs in, so an id an attacker might have planted before
     * login can no longer be used (this is the fix for "session fixation").
     * Guarded so it is a harmless no-op in tests, where no real session is active.
     */
    public function regenerateId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Log the visitor out: forget who they are and give them a fresh session id
     * so nothing from the logged-in session carries over.
     */
    public function logout(): void
    {
        $this->remove('user_id');
        $this->regenerateId();
    }
}
