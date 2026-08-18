<?php

declare(strict_types=1);

namespace Felkyo\Users;

use Felkyo\Security\RateLimiter;

/**
 * Finding a player by name — and the rules that keep that from becoming a way to
 * list everybody on the site.
 *
 * @package Felkyo\Users
 *
 * WHY THIS IS A SERVICE AND NOT JUST A REPOSITORY CALL. The dangerous part of
 * player search is not the SQL, it is the policy around it: how short a query may
 * be, how many results come back, and how often somebody may ask. Those three
 * rules ARE the defence against enumeration (CLAUDE.md section 6 names it), and a
 * defence that lives inside one controller only protects that one controller.
 *
 * This class exists because that exact mistake was made: a second page was built
 * that searched players directly through the repository, and it silently shipped
 * with none of the guards — no minimum length, no cap, no rate limit. The SQL was
 * fine; the policy was simply missing, because the policy was somewhere else.
 * Now there is one door, and both pages walk through it.
 *
 * THE FOUR GUARDS, AND WHAT EACH ONE STOPS:
 *   1. PREFIX MATCHING (in ProfileRepository::searchByNamePrefix) — "mi" finds
 *      Mira but never Jasmine, so one common letter cannot return a slice of the
 *      playerbase.
 *   2. A MINIMUM LENGTH — one letter is not a search, it is a listing of everyone
 *      whose name starts with "a".
 *   3. A RESULT CAP — a wide prefix cannot be harvested in a single request.
 *   4. A RATE LIMIT — working through the alphabet is slow, and it is visible.
 *
 * Unfindable players are excluded in the query itself, not filtered out here, so
 * a careless change to a template can never reveal one.
 */
final class PlayerFinder
{
    /**
     * @param array{minimum_length: int, result_limit: int} $limits
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private ProfileRepository $profiles,
        private AvatarSet $avatars,
        private RateLimiter $rateLimiter,
        private array $limits,
        private array $rateLimit,
    ) {
    }

    /**
     * Try to find players whose name starts with what was typed.
     *
     * The client IP is passed in because the rate limit is per-IP; the caller has
     * it from the Request and this class deliberately knows nothing about HTTP.
     */
    public function find(string $rawQuery, string $clientIp): PlayerSearchResult
    {
        $query = trim($rawQuery);

        // An empty box is somebody arriving, not somebody failing. It gets an
        // invitation on screen, not a "no results" message.
        if ($query === '') {
            return PlayerSearchResult::notSearched($query);
        }

        if (mb_strlen($query) < $this->limits['minimum_length']) {
            return PlayerSearchResult::refused($query, sprintf(
                'Please type at least %d letters.',
                $this->limits['minimum_length']
            ));
        }

        if (!$this->rateLimiter->isAllowed(
            'search',
            $clientIp,
            $this->rateLimit['max_attempts'],
            $this->rateLimit['window_seconds']
        )) {
            return PlayerSearchResult::refused(
                $query,
                'That is a lot of searching — please slow down a little.'
            );
        }
        $this->rateLimiter->record('search', $clientIp);

        // Turn each profile into just the two things a page shows: a name and a
        // picture. Resolving the avatar here means no template ever learns how a
        // stored key becomes a file path — that stays inside AvatarSet, which is
        // an allow-list and therefore a safety boundary, not a lookup table.
        $players = [];
        foreach ($this->profiles->searchByNamePrefix($query, $this->limits['result_limit']) as $profile) {
            $players[] = [
                'username' => $profile->username,
                'avatarPath' => $this->avatars->imagePathFor($profile->avatarKey),
                'avatarName' => $this->avatars->nameFor($profile->avatarKey),
            ];
        }

        return PlayerSearchResult::found($query, $players);
    }
}
