<?php

declare(strict_types=1);

namespace Felkyo\Social;

use PDO;

/**
 * Postcard designs and sent postcards (M6.4).
 *
 * @package Felkyo\Social
 *
 * Designs and cards are returned as plain arrays: they are small, read-only
 * records shown on two pages, and a class each would add nothing a reader
 * needs. Every column is listed.
 */
final class PostcardRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Designs on sale, cheapest first.
     *
     * @return array<int, array{id: int, name: string, message: string, gem_price: int, art_name: ?string}>
     */
    public function designsOnSale(): array
    {
        $statement = $this->connection->query(
            "SELECT d.id, d.name, d.message, d.gem_price, a.stored_name AS art_name
               FROM postcard_designs d LEFT JOIN art_files a ON a.id = d.art_id
              WHERE d.status = 'published' ORDER BY d.gem_price, d.id"
        );

        return array_map([$this, 'designRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** One design on sale, or null (drafts can't be bought). */
    public function designOnSale(int $id): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT d.id, d.name, d.message, d.gem_price, a.stored_name AS art_name
               FROM postcard_designs d LEFT JOIN art_files a ON a.id = d.art_id
              WHERE d.id = :id AND d.status = 'published'"
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->designRow($row) : null;
    }

    public function send(int $senderId, int $recipientId, int $designId): void
    {
        $this->connection->prepare(
            'INSERT INTO postcards (sender_user_id, recipient_user_id, design_id) VALUES (:s, :r, :d)'
        )->execute([':s' => $senderId, ':r' => $recipientId, ':d' => $designId]);
    }

    /** Cards this person sent in the last day — the anti-flooding cap. */
    public function countSentSince(int $senderId, int $seconds): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM postcards WHERE sender_user_id = :s AND sent_at >= NOW() - INTERVAL ' . max(1, $seconds) . ' SECOND'
        );
        $statement->execute([':s' => $senderId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * The mantelpiece: cards a player has received, newest first, skipping
     * any from people they've since blocked.
     *
     * @return array<int, array{sender_id: int, sender: string, name: string, message: string, art_name: ?string, sent_at: string}>
     */
    public function mantelpieceOf(int $recipientId, int $limit): array
    {
        $statement = $this->connection->prepare(
            'SELECT p.sender_user_id, s.username AS sender, d.name, d.message, a.stored_name AS art_name, p.sent_at
               FROM postcards p
               JOIN users s ON s.id = p.sender_user_id
               JOIN postcard_designs d ON d.id = p.design_id
               LEFT JOIN art_files a ON a.id = d.art_id
              WHERE p.recipient_user_id = :r
                AND NOT EXISTS (SELECT 1 FROM blocks b WHERE b.user_id = :r2 AND b.blocked_user_id = p.sender_user_id)
              ORDER BY p.sent_at DESC, p.id DESC
              LIMIT ' . max(1, $limit)
        );
        $statement->execute([':r' => $recipientId, ':r2' => $recipientId]);

        return array_map(static fn (array $row): array => [
            'sender_id' => (int) $row['sender_user_id'],
            'sender' => (string) $row['sender'],
            'name' => (string) $row['name'],
            'message' => (string) $row['message'],
            'art_name' => $row['art_name'] ?? null,
            'sent_at' => (string) $row['sent_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function designRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'message' => (string) $row['message'],
            'gem_price' => (int) $row['gem_price'],
            'art_name' => $row['art_name'] ?? null,
        ];
    }
}
