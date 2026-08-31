<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * Turning a species into a snapshot, and a snapshot back into a species.
 *
 * @package Felkyo\Creatures
 *
 * The version system's two halves, split out of SpeciesEditorService so the
 * shape of "what a version holds" lives in one small file. A snapshot is
 * the whole EDITABLE state — words, numbers, flags, and the stages' art
 * REFERENCES (never bytes; art is content-addressed in the store, so a
 * version stays a few hundred bytes however big the pictures are).
 *
 * apply() writes only whitelisted fields, and deliberately NOT status:
 * restoring old words must never silently publish or unpublish — those are
 * their own audited actions.
 */
final class SpeciesSnapshots
{
    /**
     * @param string[] $stageNames from config growth.stage_start_levels keys.
     */
    public function __construct(
        private SpeciesRepository $species,
        private array $stageNames,
    ) {
    }

    /**
     * The whole editable state of one species, as the version table stores it.
     */
    public function snapshotOf(Species $species): array
    {
        $stages = [];
        foreach ($this->stageNames as $stage) {
            $stages[$stage] = [
                'sprite_art_id' => $species->spriteArtIdFor($stage),
                'blurb' => $species->blurbFor($stage),
            ];
        }

        return [
            'name' => $species->name,
            'flavour_text' => $species->flavourText,
            'lore' => $species->lore,
            'gem_price' => $species->gemPrice,
            'is_adoptable' => $species->isAdoptable,
            'is_starter' => $species->isStarter,
            'favourite_item_id' => $species->favouriteItemId,
            'disliked_item_id' => $species->dislikedItemId,
            'library_art_id' => $species->libraryArtId,
            'stages' => $stages,
        ];
    }

    /**
     * Write a snapshot's whitelisted fields back onto a species (class note).
     * Runs inside the caller's transaction.
     */
    public function apply(int $speciesId, array $snapshot): void
    {
        $this->species->updateWords(
            $speciesId,
            (string) ($snapshot['name'] ?? ''),
            $snapshot['flavour_text'] ?? null,
            $snapshot['lore'] ?? null
        );
        $this->species->updateSettings($speciesId, [
            'gem_price' => (int) ($snapshot['gem_price'] ?? 0),
            'is_adoptable' => !empty($snapshot['is_adoptable']),
            'is_starter' => !empty($snapshot['is_starter']),
            'favourite_item_id' => isset($snapshot['favourite_item_id']) ? (int) $snapshot['favourite_item_id'] : null,
            'disliked_item_id' => isset($snapshot['disliked_item_id']) ? (int) $snapshot['disliked_item_id'] : null,
        ]);
        $this->species->setLibraryArt(
            $speciesId,
            isset($snapshot['library_art_id']) ? (int) $snapshot['library_art_id'] : null
        );
        foreach ($this->stageNames as $stage) {
            $stageState = $snapshot['stages'][$stage] ?? [];
            $artId = isset($stageState['sprite_art_id']) ? (int) $stageState['sprite_art_id'] : null;
            $this->species->setStageArt($speciesId, $stage, $artId === 0 ? null : $artId);
            $this->species->setStageBlurb($speciesId, $stage, $stageState['blurb'] ?? null);
        }
    }
}
