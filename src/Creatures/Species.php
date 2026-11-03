<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * One creature species — a KIND of creature, as loaded from the database.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: a plain, read-only value object describing a species (e.g.
 * "Foxlen"). Species are content stored as data; since M2.3 they are BUILT in
 * the creator's panel — drafted, given per-stage art and words, published.
 *
 * WHERE A SPRITE COMESS FROM — one method, two sources. spriteUrlFor() is the
 * ONLY place on the whole site that turns "this species at this stage" into
 * an image address (every template calls it; none builds a path of its own —
 * that used to be copied out six times and is exactly what made art
 * impossible to replace in one click):
 *   1. If the stage has a picture linked from the art store (M2.2), the
 *      art-store URL is returned. Its name is a content hash, so replacing
 *      the picture changes the URL and no browser cache can show the old one.
 *   2. Otherwise the pre-panel convention path,
 *      public/assets/creatures/{slug}/{stage}.gif — which is how the three
 *      original species still carry their repo art. New species never use it.
 *
 * DRAFT vs PUBLISHED: a draft is a species the artist is still building. It
 * must never be ACQUIRABLE (shop, starters, exploration filter it out — see
 * SpeciesRepository) but must still RENDER, because a player may already own
 * a creature of a species that was put back into draft for rework.
 */
final class Species
{
    // The two states a species can be in. Plain constants rather than an
    // enum: they travel into SQL and templates constantly, and two values
    // do not earn a type of their own.
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /**
     * @param array<string, array{art_id: ?int, art_name: ?string, blurb: ?string}> $stageArt
     *        Per-stage editor content, keyed by stage name ('baby', ...).
     *        Loaded by SpeciesRepository alongside the row; empty for a
     *        species whose stages were never edited (the convention carries it).
     */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $flavourText,
        public readonly bool $isStarter,
        /** Can a player come to own one of these at all? */
        public readonly bool $isAdoptable,
        public readonly int $gemPrice = 0,
        /** The item this species adores, if it has one. */
        public readonly ?int $favouriteItemId = null,
        /**
         * The item this species would rather not, if it has one.
         *
         * A DISLIKE NEVER TAKES ANYTHING AWAY. The creature eats it, gets a little
         * happier, and makes a face — the face is the whole point. Nothing here can
         * leave a creature worse off than before it was fed.
         */
        public readonly ?int $dislikedItemId = null,
        public readonly string $status = self::STATUS_PUBLISHED,
        /** The library's long text (authored in M2.3; the library page is M5). */
        public readonly ?string $lore = null,
        public readonly ?int $libraryArtId = null,
        /** The library picture's stored (content-hash) name, or null. */
        public readonly ?string $libraryArtName = null,
        public readonly ?string $updatedAt = null,
        public readonly array $stageArt = [],
        /** The alternate form this copy is dressed in (M5.3), or null. */
        public readonly ?string $formName = null,
    ) {
    }

    /**
     * This species dressed in an alternate form (M5.3): the form's own
     * sprites replace the default ones stage by stage, and any stage the form
     * has not been drawn for keeps the default — so a half-finished form
     * never shows a missing picture.
     *
     * WHY A DRESSED COPY instead of teaching every template about forms:
     * nine templates already ask `$species->spriteUrlFor($stage)`. Handing
     * them a species that answers with the form's picture means every one of
     * them shows forms correctly without being touched.
     */
    public function wearing(\Felkyo\Library\SpeciesForm $form): self
    {
        $stageArt = $this->stageArt;
        foreach ($form->stageArt as $stage => $art) {
            if (!empty($art['art_name'])) {
                $stageArt[$stage] = [
                    'art_id' => $art['art_id'],
                    'art_name' => $art['art_name'],
                    'blurb' => $this->stageArt[$stage]['blurb'] ?? null,
                ];
            }
        }

        return new self(
            $this->id, $this->slug, $this->name, $this->flavourText, $this->isStarter,
            $this->isAdoptable, $this->gemPrice, $this->favouriteItemId, $this->dislikedItemId,
            $this->status, $this->lore, $this->libraryArtId, $this->libraryArtName,
            $this->updatedAt, $stageArt, $form->name,
        );
    }

    /** Does this species adore that item? */
    public function adores(int $itemId): bool
    {
        return $this->favouriteItemId !== null && $this->favouriteItemId === $itemId;
    }

    /** Would this species rather not have that item? */
    public function dislikes(int $itemId): bool
    {
        return $this->dislikedItemId !== null && $this->dislikedItemId === $itemId;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * THE sprite address for this species at a stage — see the class note
     * for the two sources. Every template goes through here.
     */
    public function spriteUrlFor(string $stage): string
    {
        $artName = $this->stageArt[$stage]['art_name'] ?? null;
        if (is_string($artName) && $artName !== '') {
            return '/art/' . $artName;
        }

        return $this->conventionPathFor($stage);
    }

    /** The art-store id linked to a stage, or null (editor screens need it). */
    public function spriteArtIdFor(string $stage): ?int
    {
        return $this->stageArt[$stage]['art_id'] ?? null;
    }

    /** The stage's own words, or null if none were written. */
    public function blurbFor(string $stage): ?string
    {
        return $this->stageArt[$stage]['blurb'] ?? null;
    }

    /** The library picture's address, or null if none is linked. */
    public function libraryArtUrl(): ?string
    {
        return $this->libraryArtName !== null && $this->libraryArtName !== ''
            ? '/art/' . $this->libraryArtName
            : null;
    }

    /**
     * The pre-panel convention path (see class note). Public because the
     * editor shows honestly which source a stage is using.
     */
    public function conventionPathFor(string $stage): string
    {
        return '/assets/creatures/' . rawurlencode($this->slug) . '/' . rawurlencode($stage) . '.gif';
    }

    /**
     * @param array<string, array{art_id: ?int, art_name: ?string, blurb: ?string}> $stageArt
     */
    public static function fromRow(array $row, array $stageArt = []): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['flavour_text'] ?? null,
            // The database stores booleans as 0/1; cast them back to real bools.
            (bool) $row['is_starter'],
            (bool) $row['is_adoptable'],
            (int) ($row['gem_price'] ?? 0),
            isset($row['favourite_item_id']) ? (int) $row['favourite_item_id'] : null,
            isset($row['disliked_item_id']) ? (int) $row['disliked_item_id'] : null,
            (string) ($row['status'] ?? self::STATUS_PUBLISHED),
            $row['lore'] ?? null,
            isset($row['library_art_id']) ? (int) $row['library_art_id'] : null,
            $row['library_art_name'] ?? null,
            $row['updated_at'] ?? null,
            $stageArt,
        );
    }
}
