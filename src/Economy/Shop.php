<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * One shop — a place that sells items for currency.
 *
 * @package Felkyo\Economy
 *
 * A plain, read-only value object. Since M2.4 a shop also carries its
 * KEEPER — a name, a greeting (the line under the shop's name), the refusal
 * line the finder says when a search finds nothing — and two pictures from
 * the art store: the keeper's portrait and the shopfront background. All
 * optional: a shop without them renders exactly as shops always did.
 *
 * DRAFT vs PUBLISHED matters the day more shops exist (M9 lists them);
 * today's single store is published and stays so.
 */
final class Shop
{
    // The two states a shop can be in (same shape as Species and Item).
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $keeperName = null,
        public readonly ?string $keeperGreeting = null,
        public readonly ?string $keeperRefusal = null,
        public readonly ?int $backgroundArtId = null,
        /** The background picture's stored (content-hash) name, or null. */
        public readonly ?string $backgroundArtName = null,
        public readonly ?int $portraitArtId = null,
        public readonly ?string $portraitArtName = null,
        public readonly string $status = self::STATUS_PUBLISHED,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['description'] ?? null,
            $row['keeper_name'] ?? null,
            $row['keeper_greeting'] ?? null,
            $row['keeper_refusal'] ?? null,
            isset($row['background_art_id']) ? (int) $row['background_art_id'] : null,
            $row['background_art_name'] ?? null,
            isset($row['portrait_art_id']) ? (int) $row['portrait_art_id'] : null,
            $row['portrait_art_name'] ?? null,
            (string) ($row['status'] ?? self::STATUS_PUBLISHED),
            $row['updated_at'] ?? null,
        );
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** The shopfront background's address, or null if none is linked. */
    public function backgroundUrl(): ?string
    {
        return $this->backgroundArtName !== null && $this->backgroundArtName !== ''
            ? '/art/' . $this->backgroundArtName
            : null;
    }

    /** The keeper's portrait address, or null if none is linked. */
    public function portraitUrl(): ?string
    {
        return $this->portraitArtName !== null && $this->portraitArtName !== ''
            ? '/art/' . $this->portraitArtName
            : null;
    }

    /**
     * What the finder says when a search finds nothing here — the keeper's
     * own words if they have been written, else a neutral line. This is
     * what replaced the hardcoded Ruinily string (M2.4).
     */
    public function refusalLine(): string
    {
        return $this->keeperRefusal ?? 'There\'s nothing like that on the shelves.';
    }
}
