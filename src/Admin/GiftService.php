<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ItemRepository;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use PDO;

/**
 * The owner gives a player something: a creature, items, or gems.
 *
 * @package Felkyo\Admin
 *
 * WHAT IT'S FOR: making things right (Skerro, 2026-08-31: if a species has
 * to go, "people could be compensated with a new pet, item or gems"),
 * prizes for events — and birthday surprises.
 *
 * WHY IT IS FENCED IN, because it makes value out of nothing:
 *  - OWNER role only (the route's AdminGate), never artist or moderator.
 *  - Never to yourself: a gift tool that can pay its own user is a
 *    duplication bug with a nice name.
 *  - Fixed caps per gift (config: admin.gifts), so a typo can't hand out
 *    a million gems.
 *  - A reason is REQUIRED, and every gift is written to the audit log with
 *    who, what, to whom and why.
 *  - Only published species and items can be given.
 */
final class GiftService
{
    public const KINDS = ['gems', 'item', 'creature'];

    /** @param array{max_gems: int, max_items: int} $caps */
    public function __construct(
        private PDO $connection,
        private UserRepository $users,
        private SpeciesRepository $species,
        private ItemRepository $items,
        private CreatureRepository $creatures,
        private InventoryRepository $inventory,
        private AuditLogRepository $auditLog,
        private array $caps,
    ) {
    }

    /**
     * @param array{kind: string, username: string, amount: int, item_id: int,
     *              species_id: int, creature_name: string, reason: string} $gift
     */
    public function give(User $actor, array $gift, string $ip): ContentEditResult
    {
        $recipient = $this->users->findByUsername(trim($gift['username']));
        if ($recipient === null) {
            return ContentEditResult::refused('There\'s nobody with that name.');
        }
        if ($recipient->id === $actor->id) {
            return ContentEditResult::refused('Gifts are for other people — not your own account.');
        }
        $reason = trim($gift['reason']);
        if ($reason === '' || mb_strlen($reason) > 200) {
            return ContentEditResult::refused('Write a short reason (up to 200 characters) — it goes in the audit log.');
        }

        $outcome = match ($gift['kind']) {
            'gems' => $this->giveGems($recipient, $gift['amount']),
            'item' => $this->giveItems($recipient, $gift['item_id'], $gift['amount']),
            'creature' => $this->giveCreature($recipient, $gift['species_id'], $gift['creature_name']),
            default => ContentEditResult::refused('Pick what to give.'),
        };

        if ($outcome->success) {
            $this->auditLog->record(new AuditEntry(
                actorUserId: $actor->id,
                action: AuditAction::GiftGiven,
                subjectType: 'user',
                subjectId: $recipient->id,
                after: ['gift' => $outcome->message, 'reason' => $reason],
                ip: $ip,
            ));
        }

        return $outcome;
    }

    private function giveGems(User $recipient, int $amount): ContentEditResult
    {
        if ($amount < 1 || $amount > $this->caps['max_gems']) {
            return ContentEditResult::refused(sprintf('Between 1 and %d gems per gift.', $this->caps['max_gems']));
        }
        $this->users->addCurrency($recipient->id, $amount);

        return ContentEditResult::ok(sprintf('%s received %d gems.', $recipient->username, $amount));
    }

    private function giveItems(User $recipient, int $itemId, int $amount): ContentEditResult
    {
        $item = $this->items->findById($itemId);
        if ($item === null || !$item->isPublished()) {
            return ContentEditResult::refused('Pick a published item.');
        }
        if ($amount < 1 || $amount > $this->caps['max_items']) {
            return ContentEditResult::refused(sprintf('Between 1 and %d items per gift.', $this->caps['max_items']));
        }

        // One transaction: all of them arrive, or none.
        $this->connection->beginTransaction();
        try {
            for ($count = 0; $count < $amount; $count++) {
                $this->inventory->addItem($recipient->id, $item->id);
            }
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        return ContentEditResult::ok(sprintf('%s received %d × %s.', $recipient->username, $amount, $item->name));
    }

    private function giveCreature(User $recipient, int $speciesId, string $name): ContentEditResult
    {
        $species = $this->species->findById($speciesId);
        if ($species === null || !$species->isPublished()) {
            return ContentEditResult::refused('Pick a published species.');
        }
        $name = trim($name) === '' ? $species->name : trim($name);
        if (mb_strlen($name) > 30) {
            return ContentEditResult::refused('A creature\'s name is 30 characters at most.');
        }

        $creature = $this->creatures->create($recipient->id, $species->id, $name);

        return ContentEditResult::ok(sprintf('%s received %s the %s.', $recipient->username, $creature->name, $species->name), $creature->id);
    }
}
