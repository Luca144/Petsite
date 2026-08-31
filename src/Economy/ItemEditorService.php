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
 * The rules of building an item: create, save, publish, revert.
 *
 * @package Felkyo\Economy
 *
 * The same shape as SpeciesEditorService, on purpose — the artist learns
 * one editor and knows them all, and a reader of one service has read them
 * all. What is specific to items:
 *
 *  - THE MARGIN RULE MOVED TO SAVE TIME. "A player may never be paid more
 *    than 80% of an item's price for selling it back" used to be enforced
 *    only by the test suite, hours after the mistake; now the save itself
 *    refuses, with the two numbers in the sentence. The fraction still
 *    comes from the one config value the test also reads.
 *  - REFERENCES ARE SHOWN, NOT DISCOVERED. Item slugs are woven into the
 *    starter gift and the exploration loot tables; slugs are minted once
 *    and never editable (signed off 2026-08-31), and referencesFor() tells
 *    the artist where an item is used so "why is this spoken for?" is
 *    answered on the page.
 *
 * EVERY SAVE LEAVES TWO TRACES (withHistory): an audit entry with
 * before/after, and a snapshot in the shared content_versions undo.
 *
 * DELIBERATELY OVER THE 300-LINE GUIDE, like its species twin: one action
 * per public method, each the same validate → mutate → trace shape;
 * splitting them would scatter one screen's rules across files that always
 * change together (the CLAUDE.md §4 escape hatch, used knowingly).
 */
final class ItemEditorService
{
    // This editor's rows in the shared content_versions table.
    private const VERSION_TYPE = 'item';

    /**
     * @param array $limits config caps: item name/description lengths, the
     *                      sell fraction, versions kept.
     * @param array<string, string[]> $referencedSlugs slug => plain-words
     *        reasonS (an item can be several things at once — acorn-treat
     *        is both a starter gift and exploration loot), assembled in the
     *        front controller from the starter gift and the loot tables.
     */
    public function __construct(
        private PDO $connection,
        private ItemRepository $items,
        private ContentVersionRepository $versions,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
        private array $limits,
        private array $referencedSlugs,
    ) {
    }

    /**
     * Start a new item: a name and a category in, a draft out.
     */
    public function create(User $actor, string $name, int $categoryId, string $ip): ContentEditResult
    {
        $name = trim($name);
        $nameProblem = $this->nameProblem($name);
        if ($nameProblem !== null) {
            return ContentEditResult::refused($nameProblem);
        }
        if (!$this->categoryExists($categoryId)) {
            return ContentEditResult::refused('Pick which kind of thing this is.');
        }

        $slug = $this->slugFrom($name);
        if ($slug === '') {
            return ContentEditResult::refused('That name has no letters or numbers to make an address from — give it at least one.');
        }
        if ($this->items->slugExists($slug)) {
            return ContentEditResult::refused(sprintf('An item already lives at the address "%s" — pick a different name.', $slug));
        }

        $created = $this->items->create($name, $slug, $categoryId);
        $this->versions->record(self::VERSION_TYPE, $created->id, $this->snapshotOf($created), $actor->id, $this->versionsKept());
        $this->audit($actor, AuditAction::ItemCreated, $created->id, null, ['name' => $name], $ip);

        return ContentEditResult::ok(sprintf('%s exists — as a draft, on no shelf until you publish.', $name), $created->id);
    }

    /**
     * Save the item's words: name and description.
     */
    public function saveWords(User $actor, int $itemId, array $words, string $ip): ContentEditResult
    {
        $current = $this->items->findById($itemId);
        if ($current === null) {
            return ContentEditResult::refused('That item is gone.');
        }

        $name = trim((string) ($words['name'] ?? ''));
        $nameProblem = $this->nameProblem($name);
        if ($nameProblem !== null) {
            return ContentEditResult::refused($nameProblem);
        }

        $description = trim((string) ($words['description'] ?? ''));
        $descriptionCap = (int) $this->limits['item_description_max_length'];
        if (mb_strlen($description) > $descriptionCap) {
            return ContentEditResult::refused(sprintf('The description is too long — %d characters is the most.', $descriptionCap));
        }

        $this->withHistory($actor, $current, AuditAction::ItemSaved, $ip, function () use ($current, $name, $description): void {
            $this->items->updateWords($current->id, $name, $description === '' ? null : $description);
        });

        return ContentEditResult::ok('Words saved.', $current->id);
    }

    /**
     * Save the item's numbers and category — where the margin rule lives.
     */
    public function saveNumbers(User $actor, int $itemId, array $numbers, string $ip): ContentEditResult
    {
        $current = $this->items->findById($itemId);
        if ($current === null) {
            return ContentEditResult::refused('That item is gone.');
        }

        $price = (int) ($numbers['price'] ?? 0);
        $sellValue = (int) ($numbers['sell_value'] ?? 0);
        $happiness = (int) ($numbers['happiness_bonus'] ?? 0);
        $energy = (int) ($numbers['energy_bonus'] ?? 0);
        $categoryId = (int) ($numbers['category_id'] ?? 0);

        if ($price < 0 || $price > 1000000 || $sellValue < 0) {
            return ContentEditResult::refused('Prices have to be between 0 and 1,000,000 gems.');
        }
        if ($happiness < 0 || $happiness > 100 || $energy < 0 || $energy > 100) {
            return ContentEditResult::refused('The treat effects go from 0 to 100.');
        }
        if (!$this->categoryExists($categoryId)) {
            return ContentEditResult::refused('That isn\'t one of the categories.');
        }

        // The margin rule, at the moment of typing (class note). floor()
        // matches the test's arithmetic exactly — one rule, two guards.
        $fraction = (float) $this->limits['maximum_sell_fraction_of_price'];
        $maximumSellValue = (int) floor($price * $fraction);
        if ($sellValue > $maximumSellValue) {
            return ContentEditResult::refused(sprintf(
                'Selling back must pay less than buying costs: at a price of %d, the sell value can be at most %d.',
                $price, $maximumSellValue
            ));
        }

        $this->withHistory($actor, $current, AuditAction::ItemSaved, $ip, function () use ($current, $price, $sellValue, $happiness, $energy, $categoryId): void {
            $this->items->updateNumbers($current->id, [
                'price' => $price,
                'sell_value' => $sellValue,
                'happiness_bonus' => $happiness,
                'energy_bonus' => $energy,
                'category_id' => $categoryId,
            ]);
        });

        return ContentEditResult::ok('Numbers saved.', $current->id);
    }

    /**
     * Link an icon from the art store (0 unlinks — the category icon
     * stands in again, as it always did).
     */
    public function chooseIcon(User $actor, int $itemId, int $artFileId, string $ip): ContentEditResult
    {
        $current = $this->items->findById($itemId);
        if ($current === null) {
            return ContentEditResult::refused('That item is gone.');
        }

        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null) {
                return ContentEditResult::refused('That picture is gone from the art store.');
            }
            if ($picture->kind !== ArtKind::Icon) {
                return ContentEditResult::refused(sprintf(
                    'That picture is filed as "%s" — an item takes an "%s". Upload it under the right kind and it will appear here.',
                    $picture->kind->label(), ArtKind::Icon->label()
                ));
            }
            $chosenId = $picture->id;
        }

        $this->withHistory($actor, $current, AuditAction::ItemSaved, $ip, function () use ($current, $chosenId): void {
            $this->items->setIconArt($current->id, $chosenId);
        });

        return ContentEditResult::ok(
            $chosenId === null ? 'Icon unlinked — the category icon stands in.' : 'Icon linked — everywhere this item shows, this is now it.',
            $current->id
        );
    }

    /**
     * Publish — the minimum is a name and a price of at least 1 gem (a
     * priceless item cannot sit on a shelf sensibly).
     */
    public function publish(User $actor, int $itemId, string $ip): ContentEditResult
    {
        $current = $this->items->findById($itemId);
        if ($current === null) {
            return ContentEditResult::refused('That item is gone.');
        }

        $missing = $this->missingForPublish($current);
        if ($missing !== []) {
            return ContentEditResult::refused('Not yet — still missing: ' . implode(', ', $missing) . '.');
        }

        $this->withHistory($actor, $current, AuditAction::ItemPublished, $ip, function () use ($current): void {
            $this->items->setStatus($current->id, Item::STATUS_PUBLISHED);
        });

        return ContentEditResult::ok(sprintf('%s is live — any shelf that lists it offers it now.', $current->name), $current->id);
    }

    /**
     * Back to draft: off every shelf, unbuyable — but everything players
     * already own keeps working exactly as before.
     */
    public function unpublish(User $actor, int $itemId, string $ip): ContentEditResult
    {
        $current = $this->items->findById($itemId);
        if ($current === null) {
            return ContentEditResult::refused('That item is gone.');
        }

        $this->withHistory($actor, $current, AuditAction::ItemUnpublished, $ip, function () use ($current): void {
            $this->items->setStatus($current->id, Item::STATUS_DRAFT);
        });

        return ContentEditResult::ok(sprintf(
            '%s is a draft again — off every shelf until you publish. What players already own keeps working.',
            $current->name
        ), $current->id);
    }

    /**
     * Restore an older version (whitelisted fields; never the status).
     */
    public function revert(User $actor, int $itemId, int $versionId, string $ip): ContentEditResult
    {
        $current = $this->items->findById($itemId);
        if ($current === null) {
            return ContentEditResult::refused('That item is gone.');
        }

        $snapshot = $this->versions->findSnapshot($versionId, self::VERSION_TYPE, $itemId);
        if ($snapshot === null) {
            return ContentEditResult::refused('That version isn\'t in this item\'s history.');
        }

        $this->withHistory($actor, $current, AuditAction::ItemReverted, $ip, function () use ($current, $snapshot): void {
            $this->applySnapshot($current->id, $snapshot);
        });

        return ContentEditResult::ok('Restored. The page below is that version again.', $current->id);
    }

    /**
     * What still blocks publishing, in the words the refusal prints.
     *
     * @return string[]
     */
    public function missingForPublish(Item $item): array
    {
        $missing = [];
        if (trim($item->name) === '') {
            $missing[] = 'a name';
        }
        if ($item->price < 1) {
            $missing[] = 'a price of at least 1';
        }

        return $missing;
    }

    /**
     * Where this item is woven into the game — shown on its editor page so
     * the artist knows what leans on it (class note).
     *
     * @return string[] plain sentences
     */
    public function referencesFor(Item $item): array
    {
        $references = [];
        foreach ($this->referencedSlugs[$item->slug] ?? [] as $reason) {
            $references[] = $reason;
        }

        $shelves = $this->items->shelfCount($item->id);
        if ($shelves > 0) {
            $references[] = $shelves === 1 ? 'on one shop shelf' : sprintf('on %d shop shelves', $shelves);
        }

        return $references;
    }

    // ---- The shared machinery (the same shape as the species editor) ----

    /**
     * Transaction, the mutation, a snapshot of the state after (plus one of
     * the state before, the first time a pre-panel item is ever touched),
     * and the audit entry. One shape, so no action can forget a trace.
     */
    private function withHistory(User $actor, Item $before, AuditAction $action, string $ip, callable $mutate): void
    {
        $this->connection->beginTransaction();
        try {
            if ($this->versions->listFor(self::VERSION_TYPE, $before->id) === []) {
                $this->versions->record(self::VERSION_TYPE, $before->id, $this->snapshotOf($before), null, $this->versionsKept());
            }

            $mutate();

            $after = $this->items->findById($before->id);
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
     * The whole editable state — what a version holds and revert restores.
     * Status is deliberately absent: restoring old numbers must never
     * silently publish or unpublish.
     */
    private function snapshotOf(Item $item): array
    {
        return [
            'name' => $item->name,
            'description' => $item->description,
            'price' => $item->price,
            'sell_value' => $item->sellValue,
            'happiness_bonus' => $item->happinessBonus,
            'energy_bonus' => $item->energyBonus,
            'category_id' => $item->category->id,
            'icon_art_id' => $item->iconArtId,
        ];
    }

    private function applySnapshot(int $itemId, array $snapshot): void
    {
        $this->items->updateWords(
            $itemId,
            (string) ($snapshot['name'] ?? ''),
            $snapshot['description'] ?? null
        );
        $this->items->updateNumbers($itemId, [
            'price' => (int) ($snapshot['price'] ?? 0),
            'sell_value' => (int) ($snapshot['sell_value'] ?? 0),
            'happiness_bonus' => (int) ($snapshot['happiness_bonus'] ?? 0),
            'energy_bonus' => (int) ($snapshot['energy_bonus'] ?? 0),
            'category_id' => (int) ($snapshot['category_id'] ?? 0),
        ]);
        $iconArtId = isset($snapshot['icon_art_id']) ? (int) $snapshot['icon_art_id'] : null;
        $this->items->setIconArt($itemId, $iconArtId === 0 ? null : $iconArtId);
    }

    private function audit(User $actor, AuditAction $action, int $itemId, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: $action,
            subjectType: 'item',
            subjectId: $itemId,
            before: $before,
            after: $after,
            ip: $ip,
        ));
    }

    private function nameProblem(string $name): ?string
    {
        if ($name === '') {
            return 'Every item needs a name.';
        }
        if (mb_strlen($name) > (int) $this->limits['item_name_max_length']) {
            return sprintf('That name is too long — %d characters is the most.', $this->limits['item_name_max_length']);
        }

        return null;
    }

    private function categoryExists(int $categoryId): bool
    {
        foreach ($this->items->allCategories() as $category) {
            if ($category->id === $categoryId) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Moss Cake" → "moss-cake". The same rule the species editor uses; the
     * fifteen lines are duplicated rather than shared because a slug helper
     * class for two callers would be machinery, not clarity.
     */
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
