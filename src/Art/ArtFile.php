<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * One picture in the art store, as the database remembers it.
 *
 * @package Felkyo\Art
 *
 * A read-only snapshot of an art_files row (the same shape as User and
 * Creature). The bytes are NOT here — they live in the ImageStore under
 * $storedName; this object is everything the site knows ABOUT them.
 */
final class ArtFile
{
    public function __construct(
        public readonly int $id,
        public readonly string $storedName,
        public readonly ArtKind $kind,
        public readonly string $originalName,
        public readonly string $type,
        public readonly int $width,
        public readonly int $height,
        public readonly int $bytes,
        public readonly bool $isAnimated,
        public readonly ?int $uploadedByUserId,
        /** The uploader's current username, joined in for display; null if the account is gone. */
        public readonly ?string $uploaderName,
        public readonly string $createdAt,
    ) {
    }

    /**
     * The address a browser fetches the picture from. Serving goes through
     * ArtController, which looks the name up before reading anything.
     */
    public function url(): string
    {
        return '/art/' . $this->storedName;
    }

    /**
     * A row from ArtRepository → an ArtFile. A row whose kind is not on the
     * allow-list (hand-edited data) is shown as a Library picture rather than
     * crashing the one screen that could fix it.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            storedName: (string) $row['stored_name'],
            kind: ArtKind::tryFrom((string) $row['kind']) ?? ArtKind::Library,
            originalName: (string) $row['original_name'],
            type: (string) $row['type'],
            width: (int) $row['width'],
            height: (int) $row['height'],
            bytes: (int) $row['bytes'],
            isAnimated: (bool) $row['is_animated'],
            uploadedByUserId: $row['uploaded_by_user_id'] === null ? null : (int) $row['uploaded_by_user_id'],
            uploaderName: isset($row['uploader_name']) ? (string) $row['uploader_name'] : null,
            createdAt: (string) $row['created_at'],
        );
    }
}
