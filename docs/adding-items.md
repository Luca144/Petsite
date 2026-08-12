# How to add an item, and how to add a category

*Written for someone who has never programmed. The first of the "how to add one"
recipes — the documents that let the world grow without a developer.*

> **From M2.4 this becomes a form in the creator's panel.** Until then it is a
> migration, which does need a developer. The recipe is written now anyway,
> because the panel screen will do exactly these steps and it helps to know what
> it is doing.

---

## Part 1 — Adding an item

An item is a thing a player can own: a treat, a sticker, a seed, a fishing rod.

### What you need to decide first

**Its name and description.** The description is the sentence a player reads on
the item's page. The artist's design document writes these in a lovely voice —
*'A soft ball of potential.'* — and that voice is worth keeping.

**What it costs, and what it sells back for.** Two separate numbers:

- `price` — what a shop charges.
- `sell_value` — what a player gets back for parting with one.

**The rule that must never be broken: a shop keeps a margin.** The buy-back can
never be more than **80%** of the price. If it were higher, a player could buy an
item and sell it straight back at a profit, over and over, and make unlimited
currency out of nothing.

The 80% ceiling lives in `config/config.php` as
`gameplay.economy.maximum_sell_fraction_of_price`. It exists because the
shopkeeper has to live off the difference too — a shop that pays what it charges
is not a shop.

A sell value of **0** means "no shop around here would buy this". That is
different from "sells for nothing": the site says so kindly and offers to throw it
away instead, rather than showing a button that takes the item and hands back zero.

**Which category it belongs to.** One of the eight below, or a new one (part 2).

### The steps

1. Add a row to `items` with the slug, name, description, price, sell value and
   category. Slugs are lowercase with hyphens: `honey-treat`.
2. If a shop should sell it, add a row to `shop_items` linking the shop to the item.
3. Drop its picture into `public/assets/items/` named after the slug —
   `honey-treat.png`. **Nothing else needs doing.** The card and the item page
   look for that file and start showing it the moment it exists; until then they
   show the category's icon as a stand-in, so nothing is ever broken-looking while
   the artwork is still being drawn.
4. Run the test suite. `ItemSellValueTest` checks every item every shop offers and
   will fail by name if the margin rule was broken.

---

## Part 2 — Adding a category

The eight that exist, taken from the artist's design document:

| Category | For |
| --- | --- |
| Ingredient | raw things — flour, milk, tomato |
| Dish | cooked food — soup, sushi, pie |
| Potion | brewed things |
| Material | claws, feathers, ore, horns |
| Seed | things you plant |
| Tool | the things that unlock an activity — rods, pickaxes |
| Sticker | decoration |
| Badge | markers that were earned |

### The three signals, and why all three

Every category says what it is in three ways at once: a **colour**, an **icon**,
and a **word**.

That is not decoration. Roughly one man in twelve cannot separate greens from
reds, so a card that leaned on colour alone would simply lose them. The icon is
recognisable at a glance but only once you have learnt it. The word never fails
anybody. Together they cost one line of markup, so there is no reason to pick.

**A category is never allowed to be colour-only.** The code enforces this by
making the name a required part of a category rather than an optional extra.

### The steps

1. **Pick a colour and add it to `public/css/theme.css`**, beside the other
   `--category-*` tokens. Never write a colour anywhere else — the theme file is
   the only place colours live, which is what lets the selectable themes of M4
   restyle everything at once.

2. **Check it is readable.** Run the test suite. `CategoryContrastTest` opens the
   stylesheet, works out whether text can actually be read on your new colour, and
   fails with the exact number if it cannot. You do not need to judge this by eye —
   which is fortunate, because judging contrast by eye is genuinely hard, and
   hardest for the person choosing the colour.

   If it fails, make the colour **lighter** until it passes.

3. **Draw an icon** in `templates/partials/item-icons.php`. Copy one of the
   existing `<symbol>` blocks, give it an id like `icon-yourkey`, and keep the
   drawing inside the 24×24 box. Use `currentColor` so it takes the colour of the
   text beside it and works in every theme.

4. **Add the row** to `item_categories`: slug, name, the token name you chose
   (`--category-yourkey`), your icon key, and a sort order. The sort order decides
   where the section appears in a player's things — it is data, so reordering
   never needs code.

5. **Run the tests again.**

### One thing to know about deleting

A category that any item still uses **cannot be deleted** — the database refuses.
That is deliberate: it means no item can ever end up pointing at a category that
has gone. Move the items to another category first, then delete.
