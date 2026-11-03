<?php

declare(strict_types=1);

namespace Felkyo\Library;

/**
 * An alternate form of a species — the same creature, a different look
 * ("Frost Foxlen", "Autumn Mossling").
 *
 * @package Felkyo\Library
 *
 * HOW A FORM CHANGES A CREATURE: only its pictures. A form has its own sprite
 * per life stage; any stage without one falls back to the species' default
 * sprite, so a form can be drawn one stage at a time and never shows a hole.
 * Name, stats, tastes and price all stay the species'. (See
 * Species::wearing(), which is the single place the swap happens.)
 *
 * Which species get forms is entirely the artist's call — pure data, made in
 * the panel on the species' own page.
 */
final class SpeciesForm
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /**
     * @param array<string, array{art_id: ?int, art_name: ?string}> $stageArt
     *        stage => the form's own sprite for it (only stages that have one).
     */
    public function __construct(
        public readonly int $id,
        public readonly int $speciesId,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $blurb,
        public readonly string $status,
        public readonly array $stageArt = [],
    ) {
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** The form's own sprite address for a stage, or null to fall back. */
    public function ownSpriteUrlFor(string $stage): ?string
    {
        $artName = $this->stageArt[$stage]['art_name'] ?? null;

        return is_string($artName) && $artName !== '' ? '/art/' . $artName : null;
    }

    /** The art-store id linked to a stage (editor screens need it). */
    public function spriteArtIdFor(string $stage): ?int
    {
        return $this->stageArt[$stage]['art_id'] ?? null;
    }
}
