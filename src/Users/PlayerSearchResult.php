<?php

declare(strict_types=1);

namespace Felkyo\Users;

/**
 * The outcome of one attempt to find a player by name.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS IS: a small, read-only answer object. PlayerFinder produces one of
 * these and a template displays it. It exists so that "a search was run and found
 * nothing" and "no search has been run yet" stay two clearly different states —
 * they deserve different words on screen, and an empty array cannot tell them
 * apart on its own.
 *
 * The players are already flattened to what a template needs (a name and a
 * picture), so no template ever has to know how an avatar key becomes a file.
 */
final class PlayerSearchResult
{
    /**
     * @param string $query    What the player typed, trimmed.
     * @param array<int, array{username: string, avatarPath: string, avatarName: string}> $players
     * @param bool   $hasSearched Whether a search actually ran. False when the box
     *               was empty, or when a guard (too short, too fast) stopped it.
     * @param string|null $notice A plain-language reason nothing was searched for,
     *               or null when there is nothing to explain.
     */
    private function __construct(
        public readonly string $query,
        public readonly array $players,
        public readonly bool $hasSearched,
        public readonly ?string $notice,
    ) {
    }

    /**
     * Nobody has searched yet — the box is empty. Not a failure, just the start.
     */
    public static function notSearched(string $query): self
    {
        return new self($query, [], false, null);
    }

    /**
     * A guard stopped the search (too short, or too many searches too fast). The
     * message says what to do instead rather than only what went wrong.
     */
    public static function refused(string $query, string $notice): self
    {
        return new self($query, [], false, $notice);
    }

    /**
     * A search ran. The list may still be empty — that means "nobody matched",
     * which is a real answer and gets its own wording on screen.
     *
     * @param array<int, array{username: string, avatarPath: string, avatarName: string}> $players
     */
    public static function found(string $query, array $players): self
    {
        return new self($query, $players, true, null);
    }
}
