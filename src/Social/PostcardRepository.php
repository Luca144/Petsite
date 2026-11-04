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
            'SELECT p.id, p.sender_user_id, s.username AS sender, d.name, d.message, a.stored_name AS art_name, p.sent_at
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
            'id' => (int) $row['id'],
            'sender_id' => (int) $row['sender_user_id'],
            'sender' => (string) $row['sender'],
            'name' => (string) $row['name'],
            'message' => (string) $row['message'],
            'art_name' => $row['art_name'] ?? null,
            'sent_at' => (string) $row['sent_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    // ---- The panel's card editor ----

    /**
     * Every design, drafts included, for the panel's list.
     *
     * @return array<int, array{id: int, name: string, message: string, gem_price: int, art_name: ?string, art_id: ?int, status: string}>
     */
    public function allDesigns(): array
    {
        $statement = $this->connection->query(
            'SELECT d.id, d.name, d.message, d.gem_price, d.art_id, d.status, a.stored_name AS art_name
               FROM postcard_designs d LEFT JOIN art_files a ON a.id = d.art_id
              ORDER BY d.status DESC, d.name'
        );

        return array_map([$this, 'designRow'], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Any design by id, drafts included, or null. */
    public function findDesign(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT d.id, d.name, d.message, d.gem_price, d.art_id, d.status, a.stored_name AS art_name
               FROM postcard_designs d LEFT JOIN art_files a ON a.id = d.art_id WHERE d.id = :id'
        );
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->designRow($row) : null;
    }

    /** A new draft design; returns its id. */
    public function createDesign(string $name, string $message): int
    {
        $this->connection->prepare("INSERT INTO postcard_designs (name, message, status) VALUES (:name, :message, 'draft')")
            ->execute([':name' => $name, ':message' => $message]);

        return (int) $this->connection->lastInsertId();
    }

    public function updateDesign(int $id, string $name, string $message, int $gemPrice): void
    {
        $this->connection->prepare(
            'UPDATE postcard_designs SET name = :name, message = :message, gem_price = :price WHERE id = :id'
        )->execute([':name' => $name, ':message' => $message, ':price' => $gemPrice, ':id' => $id]);
    }

    public function setDesignArt(int $id, ?int $artId): void
    {
        $this->connection->prepare('UPDATE postcard_designs SET art_id = :art WHERE id = :id')
            ->execute([':art' => $artId, ':id' => $id]);
    }

    /** The value is checked against 'draft' | 'published' by the caller. */
    public function setDesignStatus(int $id, string $status): void
    {
        $this->connection->prepare('UPDATE postcard_designs SET status = :status WHERE id = :id')
            ->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * How many cards of this design have been sent. A design that has been
     * sent can't be deleted (its cards sit on mantelpieces), only retired
     * to draft.
     */
    public function timesSent(int $designId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM postcards WHERE design_id = :id');
        $statement->execute([':id' => $designId]);

        return (int) $statement->fetchColumn();
    }

    public function deleteDesign(int $id): void
    {
        $this->connection->prepare('DELETE FROM postcard_designs WHERE id = :id')->execute([':id' => $id]);
    }

    private function designRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'message' => (string) $row['message'],
            'gem_price' => (int) $row['gem_price'],
            'art_name' => $row['art_name'] ?? null,
            'art_id' => isset($row['art_id']) ? (int) $row['art_id'] : null,
            'status' => (string) ($row['status'] ?? 'published'),
        ];
    }
}
