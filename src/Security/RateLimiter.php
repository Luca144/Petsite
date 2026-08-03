<?php

declare(strict_types=1);

namespace Felkyo\Security;

/**
 * Decides whether an action may be attempted again, and records attempts.
 *
 * @package Felkyo\Security
 *
 * WHAT THIS CLASS IS: the simple rule on top of the rate_limit_hits records. It
 * answers "has this identifier already used up its allowance for this action in
 * the current time window?" and lets callers record a fresh attempt.
 *
 * HOW IT IS USED (CLAUDE.md section 6): protected endpoints — login, registration
 * — call isAllowed() before doing their work, and record() to count an attempt.
 * Login records only FAILED attempts (so normal use is never punished);
 * registration records each successful sign-up (to cap account creation). Each
 * controller documents exactly which it does.
 *
 * The limits themselves (how many, over how long) come from config, so they are
 * tuned in one place — this class only applies them.
 */
final class RateLimiter
{
    public function __construct(private RateLimitRepository $repository)
    {
    }

    /**
     * Is another attempt allowed? True while the number of recent attempts is
     * still below the maximum; false once the allowance is used up.
     */
    public function isAllowed(
        string $actionKey,
        string $identifier,
        int $maxAttempts,
        int $windowSeconds
    ): bool {
        $recentAttempts = $this->repository->countRecentHits($actionKey, $identifier, $windowSeconds);

        return $recentAttempts < $maxAttempts;
    }

    /**
     * Record one attempt against the limit.
     */
    public function record(string $actionKey, string $identifier): void
    {
        $this->repository->recordHit($actionKey, $identifier);
    }
}
