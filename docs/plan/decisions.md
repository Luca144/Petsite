# Decisions

*A running record of choices that shaped the plan, written for whoever picks this
up next — no programming knowledge assumed. Each entry says what was decided, why,
and what was deliberately left out. The last part matters most: a thing left out on
purpose looks exactly like a thing forgotten, unless somebody writes it down.*

---

## 2026-08-12 — What we took from the old Felkyo, and what we left

The old prototype was gone through feature by feature. Most of it stays gone. Five
things were worth keeping, and they are recorded below.

**A note on where this came from.** The triage document these decisions refer to
(`docs/legacy/feature-inventory.md`) is not in the repository — not in the current
files and not anywhere in the project's history. The decisions were taken from the
brief itself, cross-checked against the one legacy artefact that does exist: the
database backup in `backups/`. That backup confirmed the shape of everything below.
Worth knowing if the inventory turns up later and disagrees.

---

### 1. Creatures get a character, in two tiers

**Decided.** A creature's personality now comes from two different places, and they
are kept apart on purpose:

- **Traits it earns by living** — these arrive as it grows and is cared for. The
  player can see how each was come by. They read as a record of what the creature
  has been through.
- **Traits the world gives it** — the player neither earns nor chooses these. A
  creature leaving the pound picks up a shelter trait or two. "Grateful" is a lucky
  extra on adoption, and is the only trait that does anything mechanical: a small
  bonus to how fast the creature grows.

Both kinds are **data you edit in the panel**, not something written into the code.

**Why.** An adopted creature should feel like it had a life before you met it. That
is most of what makes rehoming worth having at all — otherwise the pound is just a
second shop with sadder decoration.

**Left out on purpose: combat statistics.** The old prototype hung five fighting
stats — health, attack, defence, speed, magic — off every growth stage of every
creature. We are not doing that yet, and the reason is worth understanding.

If traits started changing stats now, we would be designing a fighting system by
accident: one trait at a time, with no idea what the fights look like, and then be
stuck with whatever we had accidentally built. The arena is M13 and has not been
designed. **Traits gain stat effects only if and when that design asks for them.**

So for now: traits are flavour, plus that one growth bonus. Nothing else. If
somebody proposes attaching a number to a trait before the arena exists, that is a
decision to raise, not a small edit.

**Left out on purpose: working out a creature's personality from a calculation.**
The old version derived a creature's traits by running its name and owner through a
mathematical formula, so the same name always produced the same personality. It is
clever and it needs no storage at all.

We are storing traits as real records instead. Three reasons: a trait a creature
*earned* has to be able to change, which a formula cannot do; the panel needs to be
able to edit them; and "why does my creature have this trait?" should have an
answer a person can read, rather than being the output of a sum.

---

### 2. You can freeze a creature at a stage you love

**Decided.** An owner can pause a creature's growth and keep it as it is. Unfreeze
it later and it works through the stages it missed **one at a time**, as it keeps
growing — rather than jumping straight to where it "should" be.

Freezing takes nothing away: a frozen creature still earns you coins when people
pet it, still gains happiness, and loses no progress. It is fully reversible.

**Why one at a time.** If you froze a baby for six months, unfreezing should not
hand you back a fully grown adult you never saw grow. The whole point of freezing
is that you liked watching this creature; catching up in one jump would take the
watching away as a reward for having used the feature.

**Why it sits in M3 and not M8 (care).** It was worth a second look, because
freezing sounds like something you do while looking after a creature. But it is not
about *looking after* — it is about **what the creature is**, in the same way its
name and its traits are. It also shares its plumbing with the pound: both need a
creature's growth to stop and start again cleanly. So it lives beside the traits.

---

### 3. Levels and ages read as words

**Decided.** Two small lists, both editable in the panel:

- **Level titles**, so "level 7" also reads as something a person can picture.
- **A friendly age ladder**, so a creature is "a few weeks old" rather than "born
  2026-08-03".

They appear anywhere creature information is already shown — no page needs
rebuilding to pick them up.

**Why.** Numbers tell you where a creature sits on a scale. Words tell you what it
*is*. Both are cheap, both are content rather than code, and both make a creature
page read like it is about an animal instead of a record in a database.

---

### 4. Decorating a room uses things you own

**Decided.** When room and profile decoration is built (M11), it will use
**ownable items** — bought or found, kept in your inventory, placed from a menu —
reusing the item system that already exists.

**Left out on purpose: the free-form decorating layer.** The old prototype let you
stack stickers, overlays and fonts freely over a page. It is genuinely the more
exciting version, and we are not building it.

Two reasons. A blank canvas where anything goes anywhere is its own year-long
project. And it lets somebody build a page that breaks on a phone, or puts pale
text on a pale background that a good number of people cannot read — and once
players have built such pages, taking the freedom back is far worse than never
having offered it.

Decoration-as-items also means each piece already has a price, a picture, a
category and a place in the shops. Skerro adds one exactly the way she adds
anything else.

**Settled now; the rest is designed later.** The screens get designed when that
milestone starts. One thing is fixed already, because it is an accessibility
requirement rather than a preference: **placing things must work by tapping and by
keyboard.** Dragging can be added on top as a convenience — never as the only way.

---

### 5. Shops can have seasonal stock

**Decided.** Every shop listing carries an optional "available from" and "available
to" date. The columns exist in the database now, ahead of the milestone that uses
them (M9), and do nothing until then.

**Why now.** Adding a column to a table nobody is using yet costs nothing. Adding
one to a table full of live data, later, is a careful job done under pressure. The
old prototype had exactly these two columns and they were the right idea.

**What it buys.** Valentine chocolates for one week in February, or the Gallows
opening for Halloween, becomes a date somebody types — not a developer's afternoon.

---

## The house rules that came out of this

Alongside the plan changes, several ways of doing things were adopted as standard.
They are written up properly in **`docs/house-patterns.md`**, with the reasoning; in
short:

- Handing something from one player to another must be safe when two requests
  arrive at the same instant.
- "Once a day" is enforced by the database, never by a check in the code.
- Money is never read and then written in two steps.
- Things that must always be true about the economy — no negative balances, no
  ghost rows, nothing sellable for more than it cost — are automated tests **and**
  database rules, not good intentions.
- Tunable numbers live in one file. Nothing is defined twice.
- A creature's level is always worked out from what it has done, never stored.
