<?php

declare(strict_types=1);

namespace Felkyo\Economy;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Admin\ContentVersionRepository;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Users\User;
use PDO;

/**
 * The rules of building a shop: its words, its keeper, its shelves.
 *
 * @package Felkyo\Economy
 *
 * The same shape as the other editors. What is specific to shops:
 *
 *  - THE SHELVES. putListing() requires the item to be PUBLISHED (a shelf
 *    slot pointing at a draft would be a listing players can never see —
 *    refusing at stocking time keeps the shelf honest), and validates the
 *    optional season dates: real dates, from not after to. Blank means
 *    always — exactly as the 2026-08-12 decision described.
 *  - TWO ART SLOTS with different kinds: the background takes an "Area
 *    background", the keeper's portrait an "NPC portrait".
 *
 * EVERY SAVE LEAVES TWO TRACES (withHistory): an audit entry and a snapshot
 * in the shared content_versions undo. A shop's snapshot includes its
 * SHELVES, so restore puts the stock back too.
 *
 * DELIBERATELY OVER THE 300-LINE GUIDE, like its two editor twins: one
 * action per public method, each the same validate → mutate → trace shape;
 * splitting them would scatter one screen's rules across files that always
 * change together (the CLAUDE.md §4 escape hatch, used knowingly).
 */
final class ShopEditorService
{
    // This editor's rows in the shared content_versions table.
    private const VERSION_TYPE = 'shop';

    /**
     * @param array $limits config caps: shop greeting/refusal lengths,
     *                      versions kept (and the shared name cap).
     */
    public function __construct(
        private PDO $connection,
        private ShopRepository $shops,
        private ItemRepository $items,
        private ContentVersionRepository $versions,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
        private array $limits,
    ) {
    }

    /**
     * Start a new shop: a name in, a draft out.
     */
    public function create(User $actor, string $name, string $ip): ContentEditResult
    {
        $name = trim($name);
        $nameProblem = $this->nameProblem($name);
        if ($nameProblem !== null) {
            return ContentEditResult::refused($nameProblem);
        }

        $slug = $this->slugFrom($name);
        if ($slug === '') {
            return ContentEditResult::refused('That name has no letters or numbers to make an address from — give it at least one.');
        }
        if ($this->shops->slugExists($slug)) {
            return ContentEditResult::refused(sprintf('A shop already lives at the address "%s" — pick a different name.', $slug));
        }

        $created = $this->shops->create($name, $slug);
        $this->versions->record(self::VERSION_TYPE, $created->id, $this->snapshotOf($created), $actor->id, $this->versionsKept());
        $this->audit($actor, AuditAction::ShopCreated, $created->id, null, ['name' => $name], $ip);

        return ContentEditResult::ok(sprintf('%s exists — as a draft. Stock its shelves, then publish.', $name), $created->id);
    }

    /**
     * Save the shop's own words: name and description.
     */
    public function saveWords(User $actor, int $shopId, array $words, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }

        $name = trim((string) ($words['name'] ?? ''));
        $nameProblem = $this->nameProblem($name);
        if ($nameProblem !== null) {
            return ContentEditResult::refused($nameProblem);
        }

        $description = trim((string) ($words['description'] ?? ''));
        if (mb_strlen($description) > 500) {
            return ContentEditResult::refused('The description is too long — 500 characters is the most.');
        }

        $this->withHistory($actor, $current, AuditAction::ShopSaved, $ip, function () use ($current, $name, $description): void {
            $this->shops->updateWords($current->id, $name, $description === '' ? null : $description);
        });

        return ContentEditResult::ok('Words saved.', $current->id);
    }

    /**
     * Save the keeper: who minds the counter, their greeting, their refusal.
     */
    public function saveKeeper(User $actor, int $shopId, array $keeper, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }

        $name = trim((string) ($keeper['name'] ?? ''));
        $greeting = trim((string) ($keeper['greeting'] ?? ''));
        $refusal = trim((string) ($keeper['refusal'] ?? ''));

        if (mb_strlen($name) > 60) {
            return ContentEditResult::refused('The keeper\'s name is too long — 60 characters is the most.');
        }
        $greetingCap = (int) $this->limits['shop_greeting_max_length'];
        if (mb_strlen($greeting) > $greetingCap) {
            return ContentEditResult::refused(sprintf('The greeting is too long — %d characters is the most.', $greetingCap));
        }
        $refusalCap = (int) $this->limits['shop_refusal_max_length'];
        if (mb_strlen($refusal) > $refusalCap) {
            return ContentEditResult::refused(sprintf('The refusal line is too long — %d characters is the most.', $refusalCap));
        }

        $this->withHistory($actor, $current, AuditAction::ShopSaved, $ip, function () use ($current, $name, $greeting, $refusal): void {
            $this->shops->updateKeeper($current->id, [
                'name' => $name === '' ? null : $name,
                'greeting' => $greeting === '' ? null : $greeting,
                'refusal' => $refusal === '' ? null : $refusal,
            ]);
        });

        return ContentEditResult::ok('The keeper is saved.', $current->id);
    }

    /**
     * Link a picture to one of the shop's two slots ('background' or
     * 'portrait'; 0 unlinks). Each slot takes its own kind.
     */
    public function choosePicture(User $actor, int $shopId, string $slot, int $artFileId, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }

        $requiredKind = match ($slot) {
            'background' => ArtKind::Background,
            'portrait' => ArtKind::Portrait,
            default => null,
        };
        if ($requiredKind === null) {
            return ContentEditResult::refused('That is not a place a picture can go.');
        }

        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null) {
                return ContentEditResult::refused('That picture is gone from the art store.');
            }
            if ($picture->kind !== $requiredKind) {
                return ContentEditResult::refused(sprintf(
                    'That picture is filed as "%s" — this slot takes "%s". Upload it under the right kind and it will appear here.',
                    $picture->kind->label(), $requiredKind->label()
                ));
            }
            $chosenId = $picture->id;
        }

        $this->withHistory($actor, $current, AuditAction::ShopSaved, $ip, function () use ($current, $slot, $chosenId): void {
            $this->shops->setArt($current->id, $slot, $chosenId);
        });

        return ContentEditResult::ok(
            $chosenId === null ? 'Picture unlinked.' : 'Picture linked.',
            $current->id
        );
    }

    /**
     * Put an item on the shelf, with its optional season. The item must be
     * PUBLISHED — see the class note.
     */
    public function putListing(User $actor, int $shopId, array $listing, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }

        $item = $this->items->findById((int) ($listing['item_id'] ?? 0));
        if ($item === null) {
            return ContentEditResult::refused('That item doesn\'t exist.');
        }
        if (!$item->isPublished()) {
            return ContentEditResult::refused(sprintf(
                '%s is still a draft — publish the item first, then shelve it.', $item->name
            ));
        }

        $from = $this->dateOrNull((string) ($listing['available_from'] ?? ''));
        $to = $this->dateOrNull((string) ($listing['available_to'] ?? ''));
        if ($from === false || $to === false) {
            return ContentEditResult::refused('Those dates aren\'t real dates — use the date pickers, or leave them empty for "always".');
        }
        if ($from !== null && $to !== null && $from > $to) {
            return ContentEditResult::refused('The season can\'t end before it starts — swap the two dates.');
        }

        $this->withHistory($actor, $current, AuditAction::ShopSaved, $ip, function () use ($current, $item, $from, $to): void {
            $this->shops->putListing($current->id, $item->id, $from, $to);
        });

        return ContentEditResult::ok(
            $from === null && $to === null
                ? sprintf('%s is on the shelf, all year round.', $item->name)
                : sprintf('%s is on the shelf, %s to %s.', $item->name, $from ?? 'always', $to ?? 'always'),
            $current->id
        );
    }

    /**
     * Take an item off the shelf. What players already bought stays theirs.
     */
    public function removeListing(User $actor, int $shopId, int $itemId, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }

        $item = $this->items->findById($itemId);

        $this->withHistory($actor, $current, AuditAction::ShopSaved, $ip, function () use ($current, $itemId): void {
            $this->shops->removeListing($current->id, $itemId);
        });

        return ContentEditResult::ok(sprintf(
            '%s is off the shelf. Anyone who already bought one keeps it.',
            $item->name ?? 'That item'
        ), $current->id);
    }

    /**
     * Publish (the minimum is simply a name), and back to draft.
     */
    public function publish(User $actor, int $shopId, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }
        if (trim($current->name) === '') {
            return ContentEditResult::refused('Not yet — the shop needs a name.');
        }

        $this->withHistory($actor, $current, AuditAction::ShopPublished, $ip, function () use ($current): void {
            $this->shops->setStatus($current->id, Shop::STATUS_PUBLISHED);
        });

        return ContentEditResult::ok(sprintf('%s is live.', $current->name), $current->id);
    }

    public function unpublish(User $actor, int $shopId, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }

        $this->withHistory($actor, $current, AuditAction::ShopUnpublished, $ip, function () use ($current): void {
            $this->shops->setStatus($current->id, Shop::STATUS_DRAFT);
        });

        return ContentEditResult::ok(sprintf('%s is a draft again.', $current->name), $current->id);
    }

    /**
     * Restore an older version — words, keeper, pictures AND shelves.
     */
    public function revert(User $actor, int $shopId, int $versionId, string $ip): ContentEditResult
    {
        $current = $this->shops->findById($shopId);
        if ($current === null) {
            return ContentEditResult::refused('That shop is gone.');
        }

        $snapshot = $this->versions->findSnapshot($versionId, self::VERSION_TYPE, $shopId);
        if ($snapshot === null) {
            return ContentEditResult::refused('That version isn\'t in this shop\'s history.');
        }

        $this->withHistory($actor, $current, AuditAction::ShopReverted, $ip, function () use ($current, $snapshot): void {
            $this->applySnapshot($current->id, $snapshot);
        });

        return ContentEditResult::ok('Restored. The page below is that version again.', $current->id);
    }

    // ---- The shared machinery (the same shape as the other editors) ----

    private function withHistory(User $actor, Shop $before, AuditAction $action, string $ip, callable $mutate): void
    {
        $this->connection->beginTransaction();
        try {
            if ($this->versions->listFor(self::VERSION_TYPE, $before->id) === []) {
                $this->versions->record(self::VERSION_TYPE, $before->id, $this->snapshotOf($before), null, $this->versionsKept());
            }

            $mutate();

            $after = $this->shops->findById($before->id);
            $afterSnapshot = $after !== null ? $this->snapshotOf($after) : [];
            $this->versions->record(self::VERSION_TYPE, $before->id, $afterSnapshot, $actor->id, $this->versionsKept());
            $this->audit($actor, $action, $before->id, $this->snapshotOf($before), $afterSnapshot, $ip);

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }
    }

    /**
     * The whole editable state, SHELVES INCLUDED (class note). Status is
     * deliberately absent, as everywhere.
     */
    private function snapshotOf(Shop $shop): array
    {
        $listings = [];
        foreach ($this->shops->listingsFor($shop->id) as $listing) {
            $listings[] = [
                'item_id' => $listing['item_id'],
                'available_from' => $listing['available_from'],
                'available_to' => $listing['available_to'],
            ];
        }

        return [
            'name' => $shop->name,
            'description' => $shop->description,
            'keeper_name' => $shop->keeperName,
            'keeper_greeting' => $shop->keeperGreeting,
            'keeper_refusal' => $shop->keeperRefusal,
            'background_art_id' => $shop->backgroundArtId,
            'portrait_art_id' => $shop->portraitArtId,
            'listings' => $listings,
        ];
    }

    private function applySnapshot(int $shopId, array $snapshot): void
    {
        $this->shops->updateWords(
            $shopId,
            (string) ($snapshot['name'] ?? ''),
            $snapshot['description'] ?? null
        );
        $this->shops->updateKeeper($shopId, [
            'name' => $snapshot['keeper_name'] ?? null,
            'greeting' => $snapshot['keeper_greeting'] ?? null,
            'refusal' => $snapshot['keeper_refusal'] ?? null,
        ]);
        $background = isset($snapshot['background_art_id']) ? (int) $snapshot['background_art_id'] : null;
        $portrait = isset($snapshot['portrait_art_id']) ? (int) $snapshot['portrait_art_id'] : null;
        $this->shops->setArt($shopId, 'background', $background === 0 ? null : $background);
        $this->shops->setArt($shopId, 'portrait', $portrait === 0 ? null : $portrait);

        // The shelves: clear, then restock from the snapshot. Runs inside
        // withHistory's transaction, so half a shelf can never be the result.
        foreach ($this->shops->listingsFor($shopId) as $existing) {
            $this->shops->removeListing($shopId, $existing['item_id']);
        }
        foreach ((array) ($snapshot['listings'] ?? []) as $listing) {
            $this->shops->putListing(
                $shopId,
                (int) ($listing['item_id'] ?? 0),
                $listing['available_from'] ?? null,
                $listing['available_to'] ?? null
            );
        }
    }

    private function audit(User $actor, AuditAction $action, int $shopId, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: $action,
            subjectType: 'shop',
            subjectId: $shopId,
            before: $before,
            after: $after,
            ip: $ip,
        ));
    }

    private function nameProblem(string $name): ?string
    {
        if ($name === '') {
            return 'Every shop needs a name.';
        }
        if (mb_strlen($name) > 60) {
            return 'That name is too long — 60 characters is the most.';
        }

        return null;
    }

    /**
     * '' → null (always available); a real Y-m-d date → itself; anything
     * else → false (refused by the caller). checkdate keeps 2026-02-31 out.
     *
     * @return string|null|false
     */
    private function dateOrNull(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return false;
        }
        if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return false;
        }

        return $value;
    }

    /** Same rule as every editor; duplicated for the same reason. */
    private function slugFrom(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim(substr($slug, 0, 50), '-');
    }

    private function versionsKept(): int
    {
        return (int) $this->limits['species_versions_kept'];
    }
}
