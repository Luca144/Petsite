# Areas — a guide for the artist

This is for Skerro. It walks through building a whole place to explore, in
one sitting: its scene, where players can search, and what searching finds.

---

## The area's page in the panel

Panel → **Areas** → pick an area (or start a new one — it arrives as a
draft, off every map until published). Everything lives on one page, each
box with its own save button:

- **Names & words.** The area's name and the line of description under it
  on the real page.
- **The scene & its spots.** The picture players search, and the places
  they can click.
- **The loot table.** What searching finds, weighted.

The **See the real page** button on a published area is the preview: what
you saved is what players see, within the minute.

## The scene

Upload the picture under **Pictures** as an *Area background* first, then
**Choose the scene picture** on the area's page. Without a picture the
scene wears the site's painted lamplight glow — that's fine to publish
with; the picture can come later.

## The spots

A spot is a place on the scene a player can click to search. **Click the
scene preview to place one; click a marker to take it away.** Every click
fills in the number pair below the scene — the numbers are what actually
gets saved (each is a percentage across the scene, left and down), so you
can also type or arrow-key them for fine placement, and everything works
without a mouse. Blank rows are ignored; twenty spots is the most a scene
holds. An area needs at least one spot to publish.

## The loot table

One reward per line, three parts separated by semicolons:

```
45; nothing;            Only leaves and a soft wind. Nothing this time.
25; item: acorn-treat;  A crunchy little acorn, tucked under a root.
15; creature;           Something small blinks in the undergrowth!
```

- **The weight** says how often, relative to the other lines — they don't
  need to add up to 100. A weight of at least 1, always.
- **The what** is `nothing`, `creature` (a new creature of a random
  adoptable species), or `item:` followed by the item's address (the slug
  from its page under **Items**).
- **The message** is what the player reads. It can contain semicolons.

Saving takes **all lines or none**: if anything is off, nothing changes,
every problem is named by line number, and the field keeps exactly what
you typed — a typo never costs you the rest of the table. A line naming a
*draft* item saves with a heads-up (it grants fine, but players can't see
the item's page until you publish it).

An item on a loot table can't be retired out from under the area — the
Items screen will say "found while exploring …" if you try.

## Publishing

**Publish** needs a name, at least one spot, and at least one loot line —
the button names whatever is still missing. **Back to draft** takes the
area off the map again (players who found things keep them, and their
visit history survives). There is no delete: a place players have been is
part of their story, so retiring it IS taking it back to draft.

## Mistakes and history

Every save is kept in **Versions** (the newest twenty). *Restore this*
brings back that moment — words, scene picture, **the spots and the whole
loot table together**. Restoring never publishes or unpublishes by itself.

## The address is forever

The area's address (`/explore/whispering-wood`) is minted from its first
name and never changes, even if you rename the area — links and player
history stay good. If you want a different address, that's a new area.
