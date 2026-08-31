<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Admin\ContentVersionRepository;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Economy\ItemRepository;
use Felkyo\Users\User;
use PDO;

/**
 * The rules of building an area: words, background, spots, and the loot.
 *
 * @package Felkyo\Exploration
 *
 * The same shape as every editor. What is specific to areas:
 *
 *  - THE LOOT SAVES AS ONE TABLE, from the plain-line field, all-or-nothing
 *    (LootLineParser owns the format and the per-line errors). A table that
 *    would crash the reward roll — empty, or any weight below 1 — is
 *    unwritable by construction.
 *  - SPOTS SAVE AS ONE SET of x/y pairs, each clamped to 0–100.
 *  - Slugs are minted once and never editable: exploration_visits has
 *    always referenced areas by slug, and history must survive edits.
 *
 * EVERY SAVE LEAVES TWO TRACES (withHistory): an audit entry and a snapshot
 * in the shared content_versions undo — an area's snapshot includes its
 * spots AND its loot rows, so restore brings the whole place back.
 *
 * DELIBERATELY OVER THE 300-LINE GUIDE, like its editor twins: one action
 * per public method, the same validate → mutate → trace shape (the
 * CLAUDE.md §4 escape hatch, used knowingly).
 */
final class AreaEditorService
{
    // This editor's rows in the shared content_versions table.
    private const VERSION_TYPE = 'area';

    /**
     * @param array $limits config caps (name length, versions kept).
     */
    public function __construct(
        private PDO $connection,
        private AreaRepository $areas,
        private ItemRepository $items,
        private ContentVersionRepository $versions,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
        private LootLineParser $parser,
        private array $limits,
    ) {
    }

    /**
     * Start a new area: a name in, a draft out.
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
        if ($this->areas->slugExists($slug)) {
            return ContentEditResult::refused(sprintf('An area already lives at the address "%s" — pick a different name.', $slug));
        }

        $created = $this->areas->create($name, $slug);
        $this->versions->record(self::VERSION_TYPE, $created->id, $this->snapshotOf($created), $actor->id, $this->versionsKept());
        $this->audit($actor, AuditAction::AreaCreated, $created->id, null, ['name' => $name], $ip);

        return ContentEditResult::ok(sprintf('%s exists — as a draft, off every map until you publish.', $name), $created->id);
    }

    /**
     * Save the area's words: name and description.
     */
    public function saveWords(User $actor, int $areaId, array $words, string $ip): ContentEditResult
    {
        $current = $this->areas->findById($areaId);
        if ($current === null) {
            return ContentEditResult::refused('That area is gone.');
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

        $this->withHistory($actor, $current, AuditAction::AreaSaved, $ip, function () use ($current, $name, $description): void {
            $this->areas->updateWords($current->id, $name, $description === '' ? null : $description);
        });

        return ContentEditResult::ok('Words saved.', $current->id);
    }

    /**
     * Link (or unlink, with 0) the scene's background from the art store.
     */
    public function choosePicture(User $actor, int $areaId, int $artFileId, string $ip): ContentEditResult
    {
        $current = $this->areas->findById($areaId);
        if ($current === null) {
            return ContentEditResult::refused('That area is gone.');
        }

        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null) {
                return ContentEditResult::refused('That picture is gone from the art store.');
            }
            if ($picture->kind !== ArtKind::Background) {
                return ContentEditResult::refused(sprintf(
                    'That picture is filed as "%s" — a scene takes an "%s". Upload it under the right kind and it will appear here.',
                    $picture->kind->label(), ArtKind::Background->label()
                ));
            }
            $chosenId = $picture->id;
        }

        $this->withHistory($actor, $current, AuditAction::AreaSaved, $ip, function () use ($current, $chosenId): void {
            $this->areas->setBackgroundArt($current->id, $chosenId);
        });

        return ContentEditResult::ok(
            $chosenId === null ? 'Picture unlinked — the scene keeps its painted-glow placeholder.' : 'Picture linked — the scene wears it now.',
            $current->id
        );
    }

    /**
     * Save the whole set of spots: paired x/y percentages, each 0–100.
     *
     * @param array<int, string> $xs @param array<int, string> $ys as the
     *        form posts them (spot_x[] / spot_y[]).
     */
    public function saveSpots(User $actor, int $areaId, array $xs, array $ys, string $ip): ContentEditResult
    {
        $current = $this->areas->findById($areaId);
        if ($current === null) {
            return ContentEditResult::refused('That area is gone.');
        }

        if (count($xs) !== count($ys)) {
            return ContentEditResult::refused('The spots arrived lopsided — every spot needs both an x and a y.');
        }
        if (count($xs) > 20) {
            return ContentEditResult::refused('Twenty spots is the most a scene can hold.');
        }

        $spots = [];
        foreach (array_values($xs) as $index => $xValue) {
            $x = trim((string) $xValue);
            $y = trim((string) array_values($ys)[$index]);
            // A fully blank pair is an emptied row, quietly skipped.
            if ($x === '' && $y === '') {
                continue;
            }
            if (!preg_match('/^\d+$/', $x) || !preg_match('/^\d+$/', $y)
                || (int) $x > 100 || (int) $y > 100) {
                return ContentEditResult::refused(sprintf(
                    'Spot %d isn\'t on the scene — both numbers are percentages from 0 to 100.', $index + 1
                ));
            }
            $spots[] = ['x' => (int) $x, 'y' => (int) $y];
        }

        $this->withHistory($actor, $current, AuditAction::AreaSaved, $ip, function () use ($current, $spots): void {
            $this->areas->replaceSpots($current->id, $spots);
        });

        return ContentEditResult::ok(
            $spots === [] ? 'All spots removed.' : sprintf('%d spot%s saved.', count($spots), count($spots) === 1 ? '' : 's'),
            $current->id
        );
    }

    /**
     * Save the loot table from the plain-line field — all-or-nothing, with
     * every problem named by line (the parser's contract). On refusal the
     * message carries the line errors; the CONTROLLER keeps the typed text.
     */
    public function saveLoot(User $actor, int $areaId, string $text, string $ip): ContentEditResult
    {
        $current = $this->areas->findById($areaId);
        if ($current === null) {
            return ContentEditResult::refused('That area is gone.');
        }

        $itemsBySlug = [];
        foreach ($this->items->all() as $item) {
            $itemsBySlug[$item->slug] = ['id' => $item->id, 'status' => $item->status];
        }

        $parsed = $this->parser->parse($text, $itemsBySlug);
        if ($parsed['errors'] !== []) {
            return ContentEditResult::refused('Nothing was saved. ' . implode(' ', $parsed['errors']));
        }

        $this->withHistory($actor, $current, AuditAction::AreaSaved, $ip, function () use ($current, $parsed): void {
            $this->areas->replaceLoot($current->id, $parsed['rows']);
        });

        $message = sprintf('The table is saved — %d line%s.', count($parsed['rows']), count($parsed['rows']) === 1 ? '' : 's');
        if ($parsed['warnings'] !== []) {
            $message .= ' ' . implode(' ', $parsed['warnings']);
        }

        return ContentEditResult::ok($message, $current->id);
    }

    /**
     * Publish — the minimum: a name, at least one spot, at least one loot
     * row. The background stays optional (the gradient is a fine scene).
     */
    public function publish(User $actor, int $areaId, string $ip): ContentEditResult
    {
        $current = $this->areas->findById($areaId);
        if ($current === null) {
            return ContentEditResult::refused('That area is gone.');
        }

        $missing = $this->missingForPublish($current);
        if ($missing !== []) {
            return ContentEditResult::refused('Not yet — still missing: ' . implode(', ', $missing) . '.');
        }

        $this->withHistory($actor, $current, AuditAction::AreaPublished, $ip, function () use ($current): void {
            $this->areas->setStatus($current->id, Area::STATUS_PUBLISHED);
        });

        return ContentEditResult::ok(sprintf('%s is open. Players can wander in now.', $current->name), $current->id);
    }

    public function unpublish(User $actor, int $areaId, string $ip): ContentEditResult
    {
        $current = $this->areas->findById($areaId);
        if ($current === null) {
            return ContentEditResult::refused('That area is gone.');
        }

        $this->withHistory($actor, $current, AuditAction::AreaUnpublished, $ip, function () use ($current): void {
            $this->areas->setStatus($current->id, Area::STATUS_DRAFT);
        });

        return ContentEditResult::ok(sprintf('%s is a draft again — off the map until you publish.', $current->name), $current->id);
    }

    /**
     * Restore an older version — words, background, spots AND loot.
     */
    public function revert(User $actor, int $areaId, int $versionId, string $ip): ContentEditResult
    {
        $current = $this->areas->findById($areaId);
        if ($current === null) {
            return ContentEditResult::refused('That area is gone.');
        }

        $snapshot = $this->versions->findSnapshot($versionId, self::VERSION_TYPE, $areaId);
        if ($snapshot === null) {
            return ContentEditResult::refused('That version isn\'t in this area\'s history.');
        }

        $this->withHistory($actor, $current, AuditAction::AreaReverted, $ip, function () use ($current, $snapshot): void {
            $this->applySnapshot($current->id, $snapshot);
        });

        return ContentEditResult::ok('Restored. The page below is that version again.', $current->id);
    }

    /**
     * What still blocks publishing, in the refusal's words.
     *
     * @return string[]
     */
    public function missingForPublish(Area $area): array
    {
        $missing = [];
        if (trim($area->name) === '') {
            $missing[] = 'a name';
        }
        if ($area->spots === []) {
            $missing[] = 'at least one spot to search';
        }
        if ($this->areas->lootFor($area->id) === []) {
            $missing[] = 'at least one loot line';
        }

        return $missing;
    }

    // ---- The shared machinery (the same shape as every editor) ----

    private function withHistory(User $actor, Area $before, AuditAction $action, string $ip, callable $mutate): void
    {
        $this->connection->beginTransaction();
        try {
            if ($this->versions->listFor(self::VERSION_TYPE, $before->id) === []) {
                $this->versions->record(self::VERSION_TYPE, $before->id, $this->snapshotOf($before), null, $this->versionsKept());
            }

            $mutate();

            $after = $this->areas->findById($before->id);
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
     * The whole editable state — spots and loot included, status excluded.
     */
    private function snapshotOf(Area $area): array
    {
        $loot = [];
        foreach ($this->areas->lootFor($area->id) as $row) {
            $loot[] = [
                'type' => $row['type'],
                'item_id' => $row['item_id'],
                'weight' => $row['weight'],
                'message' => $row['message'],
            ];
        }

        return [
            'name' => $area->name,
            'description' => $area->description,
            'background_art_id' => $area->backgroundArtId,
            'spots' => $area->spots,
            'loot' => $loot,
        ];
    }

    private function applySnapshot(int $areaId, array $snapshot): void
    {
        $this->areas->updateWords(
            $areaId,
            (string) ($snapshot['name'] ?? ''),
            $snapshot['description'] ?? null
        );
        $background = isset($snapshot['background_art_id']) ? (int) $snapshot['background_art_id'] : null;
        $this->areas->setBackgroundArt($areaId, $background === 0 ? null : $background);

        $spots = [];
        foreach ((array) ($snapshot['spots'] ?? []) as $spot) {
            $spots[] = ['x' => (int) ($spot['x'] ?? 0), 'y' => (int) ($spot['y'] ?? 0)];
        }
        $this->areas->replaceSpots($areaId, $spots);

        $loot = [];
        foreach ((array) ($snapshot['loot'] ?? []) as $row) {
            $itemId = isset($row['item_id']) ? (int) $row['item_id'] : null;
            $loot[] = [
                'type' => (string) ($row['type'] ?? 'nothing'),
                'item_id' => $itemId === 0 ? null : $itemId,
                'weight' => max(1, (int) ($row['weight'] ?? 1)),
                'message' => (string) ($row['message'] ?? ''),
            ];
        }
        $this->areas->replaceLoot($areaId, $loot);
    }

    private function audit(User $actor, AuditAction $action, int $areaId, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: $action,
            subjectType: 'area',
            subjectId: $areaId,
            before: $before,
            after: $after,
            ip: $ip,
        ));
    }

    private function nameProblem(string $name): ?string
    {
        if ($name === '') {
            return 'Every area needs a name.';
        }
        if (mb_strlen($name) > 60) {
            return 'That name is too long — 60 characters is the most.';
        }

        return null;
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
