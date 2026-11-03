<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\ItemRepository;
use Felkyo\Users\User;
use PDO;

/**
 * Deleting a species or an item for good — the escape hatch Skerro asked for
 * (2026-08-31): "it could be problematic in a way I hadn't realised".
 *
 * @package Felkyo\Admin
 *
 * THREE SAFEGUARDS, so it can't happen by accident or in one slip:
 *  1. Only a DRAFT can be deleted. Taking it back to draft first is the
 *     pause to reconsider, and it already stops anyone new getting one.
 *  2. The exact name must be typed to confirm.
 *  3. Nobody loses out:
 *     - a species' existing creatures become another species the artist
 *       chooses — same name, same XP, same history, new look;
 *     - an item is taken off every shelf and loot table, and everyone who
 *       owns one is paid what it was worth in gems (its shop price, or its
 *       sell value if that's higher) before it disappears.
 * All of it runs in ONE transaction, and every deletion is audit-logged with
 * what it touched.
 */
final class ContentRemovalService
{
    public function __construct(
        private PDO $connection,
        private SpeciesRepository $species,
        private ItemRepository $items,
        private AuditLogRepository $auditLog,
    ) {
    }

    /** How many creatures players have of this species (the delete form says so). */
    public function creatureCount(int $speciesId): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM creatures WHERE species_id = :id');
        $statement->execute([':id' => $speciesId]);

        return (int) $statement->fetchColumn();
    }

    /** How many of this item players own in total. */
    public function ownedCount(int $itemId): int
    {
        $statement = $this->connection->prepare('SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE item_id = :id');
        $statement->execute([':id' => $itemId]);

        return (int) $statement->fetchColumn();
    }

    public function deleteSpecies(User $actor, int $speciesId, string $typedName, int $replacementId, string $ip): ContentEditResult
    {
        $species = $this->species->findById($speciesId);
        if ($species === null) {
            return ContentEditResult::refused('That species is already gone.');
        }
        if ($species->isPublished()) {
            return ContentEditResult::refused('Take it back to draft first — that pause is on purpose.');
        }
        if (trim($typedName) !== $species->name) {
            return ContentEditResult::refused(sprintf('To confirm, type its name exactly: %s', $species->name));
        }

        $affected = $this->creatureCount($speciesId);
        $replacement = $replacementId > 0 ? $this->species->findById($replacementId) : null;
        if ($affected > 0 && ($replacement === null || $replacement->id === $speciesId || !$replacement->isPublished())) {
            return ContentEditResult::refused(sprintf(
                '%d creatures are %ss. Choose which published species they become, so nobody loses theirs.',
                $affected, $species->name
            ));
        }

        $this->inOneGo(function () use ($speciesId, $affected, $replacement): void {
            if ($affected > 0) {
                // Their alternate form belonged to the old species, so it goes.
                $this->connection->prepare('UPDATE creatures SET species_id = :new, form_id = NULL WHERE species_id = :old')
                    ->execute([':new' => $replacement->id, ':old' => $speciesId]);
            }
            $this->connection->prepare('DELETE FROM species WHERE id = :id')->execute([':id' => $speciesId]);
        });

        $this->audit($actor, AuditAction::SpeciesDeleted, 'species', $speciesId, [
            'name' => $species->name, 'creatures_moved' => $affected, 'became' => $replacement?->name,
        ], $ip);

        return ContentEditResult::ok($affected > 0
            ? sprintf('%s is deleted. Its %d creatures are %ss now, names and progress kept.', $species->name, $affected, $replacement->name)
            : sprintf('%s is deleted.', $species->name));
    }

    public function deleteItem(User $actor, int $itemId, string $typedName, string $ip): ContentEditResult
    {
        $item = $this->items->findById($itemId);
        if ($item === null) {
            return ContentEditResult::refused('That item is already gone.');
        }
        if ($item->isPublished()) {
            return ContentEditResult::refused('Take it back to draft first — that pause is on purpose.');
        }
        if (trim($typedName) !== $item->name) {
            return ContentEditResult::refused(sprintf('To confirm, type its name exactly: %s', $item->name));
        }

        $refundEach = max($item->price, $item->sellValue);
        $owned = $this->ownedCount($itemId);

        $this->inOneGo(function () use ($itemId, $refundEach): void {
            // Pay every owner first (house pattern 3: the money moves in one
            // statement per row, never read-then-write), then remove the item
            // everywhere it is referenced, then the item itself.
            $this->connection->prepare(
                'UPDATE users u JOIN inventory i ON i.user_id = u.id
                    SET u.currency_balance = u.currency_balance + (i.quantity * :each)
                  WHERE i.item_id = :item'
            )->execute([':each' => $refundEach, ':item' => $itemId]);
            foreach (['inventory', 'shop_items', 'loot_entries'] as $table) {
                $this->connection->prepare('DELETE FROM ' . $table . ' WHERE item_id = :item')->execute([':item' => $itemId]);
            }
            $this->connection->prepare('DELETE FROM items WHERE id = :id')->execute([':id' => $itemId]);
        });

        $this->audit($actor, AuditAction::ItemDeleted, 'item', $itemId, [
            'name' => $item->name, 'owned' => $owned, 'refund_each' => $refundEach,
        ], $ip);

        return ContentEditResult::ok($owned > 0
            ? sprintf('%s is deleted. %d owned were paid back at %d gems each.', $item->name, $owned, $refundEach)
            : sprintf('%s is deleted.', $item->name));
    }

    private function inOneGo(callable $work): void
    {
        $this->connection->beginTransaction();
        try {
            $work();
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }
    }

    private function audit(User $actor, AuditAction $action, string $type, int $id, array $before, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: $action,
            subjectType: $type,
            subjectId: $id,
            before: $before,
            ip: $ip,
        ));
    }
}
