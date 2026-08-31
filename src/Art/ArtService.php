<?php

declare(strict_types=1);

namespace Felkyo\Art;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\Role;
use Felkyo\Admin\RoleRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Users\User;
use PDO;

/**
 * The rules for putting a picture into the art store and taking one out.
 *
 * @package Felkyo\Art
 *
 * WHO MAY: the AdminGate has already required the Artist role (or Owner) to
 * reach the routes that call this. Uploading needs nothing more. DELETING
 * has its own rule, decided at sign-off (2026-08-21): an OWNER may delete
 * any picture; an ARTIST may delete only pictures they uploaded themselves.
 * That is checked here against the database row's uploaded_by — never
 * against anything the form sent — so a forged "this is mine" is impossible.
 *
 * TWO PLACES, ONE TRUTH: a picture is bytes in the ImageStore AND a row in
 * art_files, and they must agree. Upload writes the bytes first (a stray
 * file with no row is harmless — content-addressed, it is simply overwritten
 * by the same bytes next time), then the row and the audit entry in one
 * transaction. Delete removes the row and the audit entry in one
 * transaction, and only THEN the bytes — so a failure mid-way can leave an
 * orphan file, never a row pointing at nothing.
 */
final class ArtService
{
    public function __construct(
        private PDO $connection,
        private ArtRepository $pictures,
        private ImageStore $store,
        private AuditLogRepository $auditLog,
        private RoleRepository $roles,
        private SpeciesRepository $species,
    ) {
    }

    /**
     * Put a validated picture into the store. Uploading the same bytes twice
     * is a friendly no-op: the existing picture is pointed at, no second row.
     */
    public function upload(User $actor, UploadedImage $image, ArtKind $kind, string $ip): ArtResult
    {
        $existing = $this->pictures->findByStoredName($image->storedName());
        if ($existing !== null) {
            return ArtResult::ok(
                sprintf('That exact picture is already here, as "%s" (%s) — nothing was added.',
                    $existing->originalName, $existing->kind->label()),
                $existing
            );
        }

        $this->store->put($image->storedName(), $image->bytes);

        $this->connection->beginTransaction();
        try {
            $saved = $this->pictures->insert($image, $kind, $actor->id);

            $this->auditLog->record(new AuditEntry(
                actorUserId: $actor->id,
                action: AuditAction::ArtUploaded,
                subjectType: 'art_file',
                subjectId: $saved->id,
                after: $this->snapshot($saved),
                ip: $ip,
            ));

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        return ArtResult::ok(
            sprintf('"%s" is in — a %s, %d×%d%s.',
                $saved->originalName, strtolower($saved->kind->label()), $saved->width, $saved->height,
                $saved->isAnimated ? ', animated' : ''),
            $saved
        );
    }

    /**
     * Take a picture out of the store, under the delete rule (class note).
     */
    public function delete(User $actor, int $pictureId, string $ip): ArtResult
    {
        $picture = $this->pictures->findById($pictureId);
        if ($picture === null) {
            return ArtResult::refused('That picture is already gone.');
        }

        if (!$this->mayDelete($actor, $picture)) {
            return ArtResult::refused(
                'You can only delete pictures you uploaded yourself. An owner can delete any picture.'
            );
        }

        // The guard M2.2 promised M2.3 would bring: a picture a species is
        // USING refuses deletion, and the refusal says who — never a dead
        // end, always the way to proceed (golden rule 3).
        $usedBy = $this->species->namesUsingArt($picture->id);
        if ($usedBy !== []) {
            return ArtResult::refused(sprintf(
                '%s still uses this picture — unlink it on the species page first, then it can go.',
                implode(' and ', array_slice($usedBy, 0, 3))
            ));
        }

        $this->connection->beginTransaction();
        try {
            $this->pictures->delete($picture->id);

            $this->auditLog->record(new AuditEntry(
                actorUserId: $actor->id,
                action: AuditAction::ArtDeleted,
                subjectType: 'art_file',
                subjectId: $picture->id,
                before: $this->snapshot($picture),
                ip: $ip,
            ));

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        // Only after the row is gone (class note: never a row pointing at nothing).
        $this->store->delete($picture->storedName);

        return ArtResult::ok(sprintf('"%s" is deleted.', $picture->originalName));
    }

    /**
     * The delete rule, read fresh from the database: owners may delete
     * anything; everyone else only what they uploaded.
     */
    public function mayDelete(User $actor, ArtFile $picture): bool
    {
        if (in_array(Role::Owner, $this->roles->rolesFor($actor->id), true)) {
            return true;
        }

        return $picture->uploadedByUserId !== null && $picture->uploadedByUserId === $actor->id;
    }

    /**
     * What the audit log remembers about a picture — enough to know what
     * was there even after the bytes are gone.
     */
    private function snapshot(ArtFile $picture): array
    {
        return [
            'name' => $picture->originalName,
            'kind' => $picture->kind->value,
            'stored' => $picture->storedName,
            'size' => $picture->width . 'x' . $picture->height,
            'bytes' => $picture->bytes,
        ];
    }
}
