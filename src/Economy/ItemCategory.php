<?php

declare(strict_types=1);

namespace Felkyo\Economy;

/**
 * What kind of thing an item is — a dish, a tool, a sticker.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: a plain, read-only value object for one row of item_categories.
 * A category carries the three things a player needs in order to recognise an
 * item at a glance, and it carries all three deliberately:
 *
 *   the tint  — fastest to read, and useless on its own
 *   the icon  — recognisable without reading
 *   the name  — the one that never fails anybody
 *
 * CLAUDE.md requires that colour is never the only signal, and this class is
 * where that rule is kept honest: there is no way to render a category badge
 * from this object without also having its name, because the name is not
 * optional. A card cannot accidentally become colour-only.
 *
 * Categories are content. Adding a ninth is a row in the database, and from M2.4
 * it is a form in the panel — never a code change.
 */
final class ItemCategory
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $colourToken,
        public readonly string $iconKey,
        public readonly int $sortOrder,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['slug'],
            $row['name'],
            $row['colour_token'],
            $row['icon_key'],
            (int) $row['sort_order'],
        );
    }

    /**
     * The category's colour, ready to drop into a style attribute.
     *
     * This returns "var(--category-dish)" rather than a colour value, and that is
     * the whole point: the template never learns what the colour actually is. The
     * theme file stays the only place a colour is written down, so the selectable
     * themes of M4 can restyle every category at once, and no component can drift
     * into inventing its own shade.
     *
     * The token name comes from our own database rather than from a player, but
     * it is still checked against the shape a token may have before being placed
     * in a style attribute. A value that ends up inside CSS deserves that much
     * care regardless of where we believe it came from — believing is not knowing,
     * and the panel will eventually let a human type this field.
     */
    public function colourVariable(): string
    {
        if (!preg_match('/^--[a-z0-9-]+$/', $this->colourToken)) {
            // Fall back to the neutral panel colour rather than emitting something
            // unexpected into a stylesheet. A wrong-but-plain card is a small
            // cosmetic bug; unchecked text inside CSS is not.
            return 'var(--panel)';
        }

        return 'var(' . $this->colourToken . ')';
    }
}
