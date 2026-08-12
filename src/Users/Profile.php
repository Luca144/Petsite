<?php

declare(strict_types=1);

namespace Felkyo\Users;

/**
 * One player's public page, as loaded from the database.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS IS: a plain, read-only value object holding only what a profile page
 * shows. It is a deliberately smaller thing than User: no email address, no
 * password hash, no balance.
 *
 * WHY A SEPARATE CLASS RATHER THAN REUSING User: a profile is rendered on a page
 * that anybody can open, including somebody who is not logged in. If the template
 * were handed a whole User, then every private column would be one typo away from
 * appearing in public — and the typo would look perfectly reasonable in review.
 * Handing it an object that simply does not contain those fields means the mistake
 * cannot be made. The type is the protection.
 */
final class Profile
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $avatarKey,
        public readonly ?string $about,
        public readonly ?string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['username'],
            $row['avatar_key'],
            $row['about'] ?? null,
            $row['created_at'] ?? null,
        );
    }

    /**
     * Has this player written anything about themselves yet?
     *
     * Used so the page can say something warm in the empty case rather than
     * leaving a blank space that looks like a fault.
     */
    public function hasAbout(): bool
    {
        return $this->about !== null && trim($this->about) !== '';
    }
}
