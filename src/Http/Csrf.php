<?php

declare(strict_types=1);

namespace Felkyo\Http;

use Felkyo\Auth\Session;

/**
 * Protects forms against Cross-Site Request Forgery (CSRF).
 *
 * @package Felkyo\Http
 *
 * WHAT CSRF IS: a trick where another website secretly makes your logged-in
 * browser submit a form to our site (e.g. to change your account) without your
 * intent. We stop it by putting a secret, per-session token in every form. A
 * forged request from another site cannot know that token, so it fails our check.
 *
 * HOW WE USE IT: the csrf_field() template helper drops the current token into
 * every form as a hidden field. When a form is submitted, the controller calls
 * isValid() with the submitted token before doing anything that changes data.
 */
final class Csrf
{
    // The name we store the token under in the session.
    private const SESSION_KEY = 'csrf_token';

    public function __construct(private Session $session)
    {
    }

    /**
     * Get the current session's CSRF token, creating one the first time it is
     * needed. The same token is reused for the whole session, which is enough to
     * prove a form came from our own pages.
     */
    public function token(): string
    {
        $existing = $this->session->get(self::SESSION_KEY);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        // 32 random bytes shown as hex — long and unpredictable enough that it
        // cannot be guessed by an attacker.
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Throw the current token away and mint a fresh one. Called when someone
     * LOGS IN (alongside the session id regeneration): a token handed out
     * before authentication must not stay valid after it, or a token an
     * attacker captured from the logged-out pages would still work against
     * the logged-in account. Cheap hardening the admin panel (M2.1) should
     * not run without — and every account benefits equally.
     */
    public function rotate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Check a submitted token against the one in the session. Returns true only
     * if they match exactly. We use hash_equals(), which compares in constant
     * time so an attacker cannot learn the token by timing our responses.
     */
    public function isValid(?string $submittedToken): bool
    {
        $storedToken = $this->session->get(self::SESSION_KEY);

        if (!is_string($storedToken) || $storedToken === '' || !is_string($submittedToken)) {
            return false;
        }

        return hash_equals($storedToken, $submittedToken);
    }
}
