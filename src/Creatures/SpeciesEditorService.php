<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentVersionRepository;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Users\User;
use PDO;

/**
 * The rules of building a species: create, save, publish, revert.
 *
 * @package Felkyo\Creatures
 *
 * WHO MAY: the AdminGate has already required the Artist role (or Owner) for
 * every route that reaches this; there is no per-species ownership — the
 * content belongs to the site. What this class enforces is the CONTENT
 * rules: values against allow-lists (stages from config, art of the right
 * kind, status one of two words), the publish minimum signed off 2026-08-31
 * (a sprite per stage + name + flavour), and that every mutation leaves its
 * traces. The word-rules live in SpeciesWording, the version format in
 * SpeciesSnapshots — this file is the seven actions and their shared shape.
 *
 * EVERY SAVE LEAVES TWO TRACES (withHistory): an audit entry with
 * before/after, and a version snapshot for the artist's own undo. The first
 * save of a pre-panel species also snapshots the state as it was, so even
 * the seeded originals can be restored to day one.
 */
final class SpeciesEditorService
{
    private SpeciesWording $wording;
    private SpeciesSnapshots $snapshots;

    // This editor's rows in the shared content_versions table.
    private const VERSION_TYPE = 'species';

    /**
     * @param array $speciesConfig species_* caps and versions kept (config 'art').
     * @param string[] $stageNames from config growth.stage_start_levels keys —
     *        the single source of what stages exist.
     */
    public function __construct(
        private PDO $connection,
        private SpeciesRepository $species,
        private ContentVersionRepository $versions,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
        private array $speciesConfig,
        private array $stageNames,
    ) {
        // Built here rather than injected: they are this service's own two
        // halves, split into files for readability, never wired differently.
        $this->wording = new SpeciesWording($speciesConfig);
        $this->snapshots = new SpeciesSnapshots($species, $stageNames);
    }

    /**
     * Start a new species: name in, draft out.
     */
    public function create(User $actor, string $name, string $ip): SpeciesEditResult
    {
        $name = trim($name);
        $nameProblem = $this->wording->nameProblem($name);
        if ($nameProblem !== null) {
            return SpeciesEditResult::refused($nameProblem);
        }

        $slug = $this->wording->slugFrom($name);
        if ($slug === '') {
            return SpeciesEditResult::refused('That name has no letters or numbers to make an address from — give it at least one.');
        }
        if ($this->species->slugExists($slug)) {
            return SpeciesEditResult::refused(sprintf('A species already lives at the address "%s" — pick a different name.', $slug));
        }

        $created = $this->species->create($name, $slug);
        $this->versions->record(self::VERSION_TYPE, $created->id, $this->snapshots->snapshotOf($created), $actor->id, $this->versionsKept());
        $this->audit($actor, AuditAction::SpeciesCreated, $created->id, null, ['name' => $name], $ip);

        return SpeciesEditResult::ok(sprintf('%s exists — as a draft, invisible to players until you publish.', $name), $created->id);
    }

    /**
     * Save the words: name, flavour text, lore.
     */
    public function saveWords(User $actor, int $speciesId, array $words, string $ip): SpeciesEditResult
    {
        $current = $this->species->findById($speciesId);
        if ($current === null) {
            return SpeciesEditResult::refused('That species is gone.');
        }

        $name = trim((string) ($words['name'] ?? ''));
        $flavour = $this->wording->textOrNull((string) ($words['flavour_text'] ?? ''), $this->wording->blurbCap(), 'flavour text');
        $lore = $this->wording->textOrNull((string) ($words['lore'] ?? ''), $this->wording->loreCap(), 'lore');

        $nameProblem = $this->wording->nameProblem($name);
        if ($nameProblem !== null) {
            return SpeciesEditResult::refused($nameProblem);
        }
        if ($flavour instanceof SpeciesEditResult) {
            return $flavour;
        }
        if ($lore instanceof SpeciesEditResult) {
            return $lore;
        }

        $this->withHistory($actor, $current, AuditAction::SpeciesSaved, $ip, function () use ($current, $name, $flavour, $lore): void {
            $this->species->updateWords($current->id, $name, $flavour, $lore);
        });

        return SpeciesEditResult::ok('Words saved.', $current->id);
    }

    /**
     * Save one stage's blurb.
     */
    public function saveStageBlurb(User $actor, int $speciesId, string $stage, string $blurb, string $ip): SpeciesEditResult
    {
        $current = $this->species->findById($speciesId);
        if ($current === null) {
            return SpeciesEditResult::refused('That species is gone.');
        }
        if (!in_array($stage, $this->stageNames, true)) {
            return SpeciesEditResult::refused('That is not one of the growth stages.');
        }

        $checked = $this->wording->textOrNull($blurb, $this->wording->blurbCap(), 'blurb');
        if ($checked instanceof SpeciesEditResult) {
            return $checked;
        }

        $this->withHistory($actor, $current, AuditAction::SpeciesSaved, $ip, function () use ($current, $stage, $checked): void {
            $this->species->setStageBlurb($current->id, $stage, $checked);
        });

        return SpeciesEditResult::ok(sprintf('The %s words are saved.', $stage), $current->id);
    }

    /**
     * Link a picture to a stage, or to the library slot ('library'). 0 unlinks.
     */
    public function choosePicture(User $actor, int $speciesId, string $slot, int $artFileId, string $ip): SpeciesEditResult
    {
        $current = $this->species->findById($speciesId);
        if ($current === null) {
            return SpeciesEditResult::refused('That species is gone.');
        }

        $isLibrary = $slot === 'library';
        if (!$isLibrary && !in_array($slot, $this->stageNames, true)) {
            return SpeciesEditResult::refused('That is not a place a picture can go.');
        }

        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null) {
                return SpeciesEditResult::refused('That picture is gone from the art store.');
            }
            // The shelf discipline: stages take sprites, the library slot
            // takes library art. Uploading to the right shelf is what makes
            // the pickers short enough to scan.
            $requiredKind = $isLibrary ? ArtKind::Library : ArtKind::Sprite;
            if ($picture->kind !== $requiredKind) {
                return SpeciesEditResult::refused(sprintf(
                    'That picture is filed as "%s" — this slot takes "%s". Upload it under the right kind and it will appear here.',
                    $picture->kind->label(), $requiredKind->label()
                ));
            }
            $chosenId = $picture->id;
        }

        $this->withHistory($actor, $current, AuditAction::SpeciesSaved, $ip, function () use ($current, $isLibrary, $slot, $chosenId): void {
            if ($isLibrary) {
                $this->species->setLibraryArt($current->id, $chosenId);
            } else {
                $this->species->setStageArt($current->id, $slot, $chosenId);
            }
        });

        return SpeciesEditResult::ok(
            $chosenId === null ? 'Picture unlinked.' : 'Picture linked — everywhere this stage shows, this is now it.',
            $current->id
        );
    }

    /**
     * Save the numbers and flags: price, adoptable, starter, tastes.
     */
    public function saveSettings(User $actor, int $speciesId, array $settings, string $ip): SpeciesEditResult
    {
        $current = $this->species->findById($speciesId);
        if ($current === null) {
            return SpeciesEditResult::refused('That species is gone.');
        }

        $gemPrice = (int) ($settings['gem_price'] ?? 0);
        if ($gemPrice < 0 || $gemPrice > 1000000) {
            return SpeciesEditResult::refused('The price has to be between 0 and 1,000,000 gems.');
        }

        $favourite = $this->itemIdOrNull((string) ($settings['favourite_item_id'] ?? ''));
        $disliked = $this->itemIdOrNull((string) ($settings['disliked_item_id'] ?? ''));
        if ($favourite instanceof SpeciesEditResult) {
            return $favourite;
        }
        if ($disliked instanceof SpeciesEditResult) {
            return $disliked;
        }

        $this->withHistory($actor, $current, AuditAction::SpeciesSaved, $ip, function () use ($current, $gemPrice, $settings, $favourite, $disliked): void {
            $this->species->updateSettings($current->id, [
                'gem_price' => $gemPrice,
                'is_adoptable' => !empty($settings['is_adoptable']),
                'is_starter' => !empty($settings['is_starter']),
                'favourite_item_id' => $favourite,
                'disliked_item_id' => $disliked,
            ]);
        });

        return SpeciesEditResult::ok('Settings saved.', $current->id);
    }

    /**
     * Publish — after the signed-off minimum is met. The refusal names every
     * missing piece, so "why can't I publish?" never needs a developer.
     */
    public function publish(User $actor, int $speciesId, string $ip): SpeciesEditResult
    {
        $current = $this->species->findById($speciesId);
        if ($current === null) {
            return SpeciesEditResult::refused('That species is gone.');
        }

        $missing = $this->missingForPublish($current);
        if ($missing !== []) {
            return SpeciesEditResult::refused('Not yet — still missing: ' . implode(', ', $missing) . '.');
        }

        $this->withHistory($actor, $current, AuditAction::SpeciesPublished, $ip, function () use ($current): void {
            $this->species->setStatus($current->id, Species::STATUS_PUBLISHED);
        });

        return SpeciesEditResult::ok(sprintf('%s is live. Players can meet it now.', $current->name), $current->id);
    }

    /**
     * Back to draft. Existing creatures keep living and rendering; the
     * species just leaves the shop and the starter pool.
     */
    public function unpublish(User $actor, int $speciesId, string $ip): SpeciesEditResult
    {
        $current = $this->species->findById($speciesId);
        if ($current === null) {
            return SpeciesEditResult::refused('That species is gone.');
        }

        $this->withHistory($actor, $current, AuditAction::SpeciesUnpublished, $ip, function () use ($current): void {
            $this->species->setStatus($current->id, Species::STATUS_DRAFT);
        });

        return SpeciesEditResult::ok(sprintf(
            '%s is a draft again. Creatures people already have keep living; nobody new can get one until you publish.',
            $current->name
        ), $current->id);
    }

    /**
     * Restore an older version. The snapshot is server-written JSON looked
     * up by id AND species (a version id from another species finds
     * nothing); SpeciesSnapshots applies only whitelisted fields.
     */
    public function revert(User $actor, int $speciesId, int $versionId, string $ip): SpeciesEditResult
    {
        $current = $this->species->findById($speciesId);
        if ($current === null) {
            return SpeciesEditResult::refused('That species is gone.');
        }

        $snapshot = $this->versions->findSnapshot($versionId, self::VERSION_TYPE, $speciesId);
        if ($snapshot === null) {
            return SpeciesEditResult::refused('That version isn\'t in this species\' history.');
        }

        $this->withHistory($actor, $current, AuditAction::SpeciesReverted, $ip, function () use ($current, $snapshot): void {
            $this->snapshots->apply($current->id, $snapshot);
        });

        return SpeciesEditResult::ok('Restored. The page below is that version again.', $current->id);
    }

    /**
     * The publish minimum (signed off 2026-08-31): a sprite for every
     * stage, a name, flavour text. Returned as the plain words the refusal
     * prints.
     *
     * @return string[]
     */
    public function missingForPublish(Species $species): array
    {
        $missing = [];
        if (trim($species->name) === '') {
            $missing[] = 'a name';
        }
        if (trim((string) $species->flavourText) === '') {
            $missing[] = 'the flavour text';
        }
        foreach ($this->stageNames as $stage) {
            // A linked art-store picture counts; so does repo art carried by
            // the old convention (the three original species) — what matters
            // is that the stage SHOWS something, and the editor displays
            // which source each stage is using.
            if ($species->spriteArtIdFor($stage) === null && !$this->conventionArtExists($species, $stage)) {
                $missing[] = sprintf('a picture for the %s stage', $stage);
            }
        }

        return $missing;
    }

    // ---- The shared machinery ----

    /**
     * Every mutation goes through here: transaction, the mutation itself,
     * a version snapshot of the state AFTER (plus one of the state BEFORE,
     * the first time a pre-panel species is ever touched), and the audit
     * entry with before/after. One shape, so no action can forget a trace.
     */
    private function withHistory(User $actor, Species $before, AuditAction $action, string $ip, callable $mutate): void
    {
        $this->connection->beginTransaction();
        try {
            if ($this->versions->listFor(self::VERSION_TYPE, $before->id) === []) {
                // A species from before the panel: keep its day-one state
                // restorable before the first edit changes it.
                $this->versions->record(self::VERSION_TYPE, $before->id, $this->snapshots->snapshotOf($before), null, $this->versionsKept());
            }

            $mutate();

            $after = $this->species->findById($before->id);
            $afterSnapshot = $after !== null ? $this->snapshots->snapshotOf($after) : [];
            $this->versions->record(self::VERSION_TYPE, $before->id, $afterSnapshot, $actor->id, $this->versionsKept());
            $this->audit($actor, $action, $before->id, $this->snapshots->snapshotOf($before), $afterSnapshot, $ip);

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }
    }

    private function audit(User $actor, AuditAction $action, int $speciesId, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: $action,
            subjectType: 'species',
            subjectId: $speciesId,
            before: $before,
            after: $after,
            ip: $ip,
        ));
    }

    /**
     * An item id from a form: '' means none, otherwise it must exist. One
     * COUNT query — items have no repository of their own yet; when they
     * get one (M2.4), this moves there.
     *
     * @return int|null|SpeciesEditResult
     */
    private function itemIdOrNull(string $formValue)
    {
        $formValue = trim($formValue);
        if ($formValue === '' || $formValue === '0') {
            return null;
        }

        $itemId = (int) $formValue;
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM items WHERE id = :id');
        $statement->execute([':id' => $itemId]);
        if ((int) $statement->fetchColumn() === 0) {
            return SpeciesEditResult::refused('That item doesn\'t exist.');
        }

        return $itemId;
    }

    /**
     * Does the pre-panel convention have a file for this stage? Only the
     * three original species ever say yes; the check reads the real file
     * the templates would serve.
     */
    private function conventionArtExists(Species $species, string $stage): bool
    {
        return is_file(dirname(__DIR__, 2) . '/public' . $species->conventionPathFor($stage));
    }

    private function versionsKept(): int
    {
        return (int) $this->speciesConfig['species_versions_kept'];
    }
}
