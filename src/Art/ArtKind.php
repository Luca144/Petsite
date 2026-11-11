<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * What a picture in the art store is FOR.
 *
 * @package Felkyo\Art
 *
 * WHY AN ENUM: the kind arrives from a form (radio cards on the Pictures
 * screen). As a plain string a request could invent a kind, and every later
 * screen would have to guess what to do with it. As an enum only these six
 * exist, and M2.3 can ask "give me every sprite" and trust the answer.
 *
 * The list is the build plan's own (M2.2: "sprites, item icons, NPC
 * portraits, card art, area backgrounds, library art"). Adding a kind is a
 * case here plus a label — the column is plain text, so no migration.
 */
enum ArtKind: string
{
    case Sprite = 'sprite';
    case Icon = 'icon';
    case Portrait = 'portrait';
    case Card = 'card';
    case Background = 'background';
    case Library = 'library';
    // A picture players may choose as their avatar (Skerro, 2026-10-14).
    case Avatar = 'avatar';
    // A keepsake badge's little picture.
    case Badge = 'badge';
    // A page background tile or a banner a theme can wear (Site look, 2026-11-11).
    case Site = 'site';

    /** What a person sees it called. */
    public function label(): string
    {
        return match ($this) {
            self::Sprite => 'Creature sprite',
            self::Icon => 'Item icon',
            self::Portrait => 'NPC portrait',
            self::Card => 'Card art',
            self::Background => 'Area background',
            self::Library => 'Library art',
            self::Avatar => 'Player avatar',
            self::Badge => 'Badge',
            self::Site => 'Site art',
        };
    }

    /**
     * One plain sentence beside each radio card, so the artist picks the
     * right shelf without a manual (golden rule 1).
     */
    public function description(): string
    {
        return match ($this) {
            self::Sprite => 'A creature at one of its stages — usually animated.',
            self::Icon => 'The small picture of an item in the shop and the satchel.',
            self::Portrait => 'A shopkeeper or other character.',
            self::Card => 'The picture on a postcard.',
            self::Background => 'The scene behind an exploration area.',
            self::Library => 'A larger illustration for the library page.',
            self::Avatar => 'A face players can choose for their own page — appears in their choices at once.',
            self::Badge => 'The small picture of a keepsake badge (about 48×48).',
            self::Site => 'A page background tile or a banner for a theme (a banner about 800×150).',
        };
    }

    /** A form value → a kind, or null if it is not one of ours. */
    public static function fromFormValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
