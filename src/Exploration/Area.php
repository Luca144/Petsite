<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

/**
 * One exploration area — a place a player can search.
 *
 * @package Felkyo\Exploration
 *
 * A plain, read-only value object. Areas were config until M2.5; now they
 * are rows the panel edits — name, description, the scene's background from
 * the art store, the clickable spots, and (loaded separately, by the
 * repository) the weighted loot table.
 *
 * DRAFT vs PUBLISHED: a draft area is the artist's alone — absent from the
 * player's area list and refused on both /explore routes. Unlike species
 * and items there is nothing a player can OWN of an area, so there is no
 * render-asymmetry to keep: drafts are simply invisible.
 */
final class Area
{
    // The two states an area can be in (same shape as every editor).
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /**
     * @param array<int, array{x: int, y: int}> $spots the clickable points,
     *        percentages across the scene, loaded with the row.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?int $backgroundArtId = null,
        /** The background picture's stored (content-hash) name, or null. */
        public readonly ?string $backgroundArtName = null,
        public readonly string $status = self::STATUS_PUBLISHED,
        public readonly ?string $updatedAt = null,
        public readonly array $spots = [],
    ) {
    }

    /**
     * @param array<int, array{x: int, y: int}> $spots
     */
    public static function fromRow(array $row, array $spots = []): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['description'] ?? null,
            isset($row['background_art_id']) ? (int) $row['background_art_id'] : null,
            $row['background_art_name'] ?? null,
            (string) ($row['status'] ?? self::STATUS_PUBLISHED),
            $row['updated_at'] ?? null,
            $spots,
        );
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * The scene's background address, or null — in which case the page keeps
     * the cosy gradient it has always had (explore.css).
     */
    public function backgroundUrl(): ?string
    {
        return $this->backgroundArtName !== null && $this->backgroundArtName !== ''
            ? '/art/' . $this->backgroundArtName
            : null;
    }
}
