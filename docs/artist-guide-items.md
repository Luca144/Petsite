# Items — a guide for the artist

This is for Skerro. It walks through creating a thing players can own, eat
and sell — in the panel, with no code and nobody else involved.

---

## Start it

Panel → **Items** → name it, pick what kind of thing it is (the category
tiles), *Start it (as a draft)*. A draft sits on no shelf and cannot be
bought, however long you take.

## Build it, saving as you go

- **Its picture.** Upload it under **Pictures** as an *Item icon*, then
  **Choose an icon** on the item's page. Until you do, the category's
  little drawing stands in — nothing looks broken meanwhile.
- **Names & words.** The name and the sentence on its card.
- **Numbers.** What it costs, and what a player gets for selling one back.
  **The one hard rule lives here: selling back pays at most 80% of the
  price** — otherwise buying and selling in a loop would print gems. The
  form refuses anything higher and tells you the exact ceiling. A sell
  value of 0 means "no shop would buy this" — players are told that in
  words, never shown a button that pays nothing.
- **Treat effects.** Happiness and energy from eating one. **Anything with
  an effect IS a treat** and appears in feeding — there is no separate
  switch to remember, and an item with both at 0 simply isn't food.
- **Category** — the tint, icon and word players recognise it by.

## Publish it

Publish needs a name and a price of at least 1. Once published, put it on
a shelf in the **Shops** editor — being published makes it *shelvable*,
the shelf makes it *for sale*.

**Changed your mind?** *Back to draft* takes it off every shelf at once.
Whatever players already own keeps working exactly as before — eating,
selling, everything.

## The fine print worth knowing

- **Names can change; addresses can't.** An item's internal address (its
  "slug") is minted from its first name and never changes — parts of the
  game hold on to it (the page says so when they do: "part of every new
  player's starter gift", "found while exploring…"). Renaming the item is
  fine; the address underneath stays.
- **There is no delete.** Players may own one; *Back to draft* is how an
  item retires from circulation.
- **Versions**, bottom of the page: every save kept (newest twenty),
  restorable with one tap. Restoring never publishes or unpublishes.
- **Adding a whole CATEGORY** (a new tint + icon) is still a written
  recipe for a coder — deliberately, until the theme editor arrives in a
  later milestone. Ask Katharina.
