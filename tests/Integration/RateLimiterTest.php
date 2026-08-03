<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;

/**
 * Tests for the rate limiter — the anti-abuse "how many attempts" logic.
 *
 * @package Felkyo\Tests\Integration
 */
final class RateLimiterTest extends DatabaseTestCase
{
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits');
        $this->rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));
    }

    public function testAttemptsAreAllowedUntilTheLimitIsReached(): void
    {
        // Policy for this test: at most 3 attempts within an hour.
        $max = 3;
        $window = 3600;

        // With no attempts yet, the next one is allowed.
        $this->assertTrue($this->rateLimiter->isAllowed('login', '10.0.0.1', $max, $window));

        // Record attempts up to the limit; each of these was still allowed.
        for ($i = 0; $i < $max; $i++) {
            $this->assertTrue($this->rateLimiter->isAllowed('login', '10.0.0.1', $max, $window));
            $this->rateLimiter->record('login', '10.0.0.1');
        }

        // Now the allowance is used up: the next attempt is blocked.
        $this->assertFalse($this->rateLimiter->isAllowed('login', '10.0.0.1', $max, $window));
    }

    public function testAttemptsOlderThanTheWindowDoNotCount(): void
    {
        // Insert an attempt stamped two hours ago, directly in the database.
        $this->connection->exec(
            "INSERT INTO rate_limit_hits (action_key, identifier, created_at)
             VALUES ('login', '10.0.0.2', NOW() - INTERVAL 2 HOUR)"
        );

        // With a 60-second window, that old attempt is outside it — so a fresh
        // attempt is still allowed.
        $this->assertTrue($this->rateLimiter->isAllowed('login', '10.0.0.2', 1, 60));
    }

    public function testDifferentIdentifiersAreCountedSeparately(): void
    {
        // One IP uses up its allowance...
        $this->rateLimiter->record('login', '10.0.0.3');
        $this->rateLimiter->record('login', '10.0.0.3');

        // ...which must not affect a different IP.
        $this->assertFalse($this->rateLimiter->isAllowed('login', '10.0.0.3', 2, 3600));
        $this->assertTrue($this->rateLimiter->isAllowed('login', '10.0.0.4', 2, 3600));
    }
}
