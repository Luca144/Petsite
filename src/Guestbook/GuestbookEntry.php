<?php

declare(strict_types=1);

namespace Felkyo\Guestbook;

/**
 * One signature in a creature's guestbook, as loaded from the database.
 *
 * @package Felkyo\Guestbook
 *
 * WHAT THIS IS: a plain, read-only value object. Note what it does NOT hold — the
 * message text. It holds the message KEY; the text is looked up from
 * GuestbookMessages when the page is rendered. That is what lets the Product Owner
 * reword a message without touching a single database row.
 *
 * The author's username travels with the entry because the repository fetches it
 * in the same query (a JOIN), so displaying a guestbook never needs a second trip
 * to the database per entry.
 */
final class GuestbookEntry
{
    public function __construct(
        public readonly int $id,
        public readonly int $creatureId,
        public readonly int $authorUserId,
        public readonly string $authorUsername,
        public readonly string $messageKey,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row A row from GuestbookRepository's queries.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['creature_id'],
            (int) $row['author_user_id'],
            (string) $row['author_username'],
            (string) $row['message_key'],
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null,
        );
    }
}
