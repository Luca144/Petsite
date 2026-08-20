# Interlude after M1 — the creature you keep

*Written 2026-08-18 under the title "M2". **Built 2026-08-18** — all four steps
shipped. This document is now the record of what was decided and why;
`docs/developer-guide.md` under "Interlude after M1 — the creature you keep" is
the map of where things live.*

> **CORRECTION (2026-08-20): this was never M2.** The agreed milestone plan —
> `felkyo-milestones.md` (the artist's copy) and `felkyo-build-plan-2.md` (the
> build plan), which agree with each other — defines **M2 as the creator's
> panel**: roles, image upload, one page per creature, moderation tools,
> settings, stats. The session that wrote this document invented a milestone
> that was not in the plan.
>
> What it built is solid work and stays — but it is an **interlude**, pulled
> forward from later milestones, not M2:
>
> | Built here | Where the plan actually has it |
> | --- | --- |
> | Petter earns gems, daily cap, cooldown | **M6.2** — happily, built *with* the farming guards M6.2 demands |
> | Happiness, energy, feeding, treats | **M8.1** (care) |
> | Mini-games | **M8.6** — which the plan marks "[DISCUSS BEFORE BUILDING]"; that discussion never happened |
> | Keepsake card, favourite star, creatures sold for gems, daily adoption retired | In no milestone — see `docs/plan/decisions.md`, entry 2026-08-20 |
>
> The real M2 — the creator's panel — had not been started when this correction
> was written. `felkyo-build-plan-2.md` carries matching "already built" notes
> at M6.2, M8.1 and M8.6 so none of this is built twice.

> **What actually shipped, against the plan below:**
>
> | Step | Planned | Built |
> | --- | --- | --- |
> | Economy | petter earns, 100/day cap, rename to gems | as planned |
> | Creature stats | happiness + energy, derived not ticked | as planned |
> | Keepsake card | mood, bars, pet + feed | as planned, **and on phones** |
> | Treats | three, differing in kind | as planned, **plus a starter gift** |
> | Shop creatures | species priced, adoption retired | as planned |
>
> **Three things changed during the build, all for reasons worth keeping:**
>
> 1. **New players are given three treats.** The plan did not notice that treats
>    cost gems and gems come from petting *other people's* creatures — so a
>    player's first minute would have been a feeding section explaining that they
>    had nothing to feed with. A dead end on the first screen.
> 2. **The favourite star was added** (Skerro asked, and M2 needed it): the
>    keepsake card only exists once a favourite is chosen, and choosing one meant
>    opening the profile settings page. A feature reached only through settings is
>    one most people never find.
> 3. **Exploring drops treats.** Without it the only way into the mood system was
>    to buy your way in, and exploring returned nothing 85 times in 100.
>
> **One open question from below was answered by building it:** daily adoption is
> gone rather than kept alongside buying. Two ways to get a creature would have
> been worse than one.
>
> **Still open:** the two art questions at the bottom (a sleepy sprite, and
> creatures preferring certain treats). Both are cheap to add and neither blocks
> anything.

M1 gave players a world to walk around. M2 is about giving them **one creature
they actually care about**, and an economy where the way you earn is the same as
the way you have fun.

---

## The problem M2 is solving

Right now a creature is a page you visit. You pet it, a number goes up, you leave.
Nothing about it is *yours* in the way a pet is yours — it does not react, it does
not need you, and it never says anything unless you go and look at it.

The fix is not more numbers. It is one creature, visible on every page, that
responds to small acts of care.

---

## Why the tamagotchi will be fun and not a chore

This is the part worth getting right, because the classic version of this
mechanic is genuinely unpleasant: a thing that decays while you are asleep and
punishes you for having a life. That design breaks golden rule 10 outright
(*never punish absence*), so we are not building it.

**What we are building instead:**

| Classic tamagotchi | Felkyo |
|---|---|
| Dies if neglected | Gets sleepy. Never dies, never leaves |
| Decays in hours | Decays a few percent a day, with a floor |
| Demands attention | Offers it — a small warm thing on the page |
| Needs a routine | Rewards a visit, whenever the visit happens |

**Where the fun actually comes from — four things, in order of importance:**

1. **Instant, visible response.** Tap "pet", a heart appears, the bar moves. The
   whole interaction is under five seconds and you can see it worked. This is the
   entire hook; everything else is decoration on top of it.
2. **It is present without being demanding.** The card sits in the sidebar on
   every page. You do not go to it — you pass it, and it is doing something.
3. **Treats make care feel specific.** Giving a Honey Treat is a different act
   from petting. Choosing one is a tiny expression of preference.
4. **Mood is legible at a glance.** A word and a face, not a percentage. "Very
   happy" and "sleepy" are states you can feel something about; "73%" is not.

**Three rules we are holding ourselves to:**

- **Nothing decays below a floor.** A creature left alone for a month is sleepy
  and pleased to see you. It is never sad, sick, or reproachful.
- **No streaks, no daily login rewards, no "you missed a day".** Those convert a
  pleasure into an obligation, which is the exact failure mode we are avoiding.
- **Never blocked from interacting.** Low energy changes the *response* (a sleepy
  animation instead of a bouncy one), it never greys out the button. Being told
  "come back later" is the least fun sentence in games.

---

## What is already built (shipped 2026-08-18)

**Petting pays the petter.** One gem, to the person doing the petting, when the
creature belongs to somebody else. The currency is called gems.

This is the change that makes the rest of M2 work: gems now arrive *by visiting
other people's creatures*, and they will be spent *on your own*. Earning and
spending are two different, both-enjoyable activities, which is what an economy
is for.

Bounded against farming three ways, all server-side and all tested to refuse:
your own creature never pays; the per-creature cooldown stops any single creature
being milked; a rolling **100 gems/day** cap per account makes a second account
worth at most that, for real clicking effort.

Petting is now also a single locked transaction, so the same request arriving
twice cannot be paid twice.

---

## What is left, in build order

### Step 1 — Creature stats (the foundation)

Two numbers on a creature, both derived from time rather than stored ticking:

- **Happiness** 0–100. Rises with petting (+5) and treats (+10). Falls ~5 a day,
  with a floor at 20. Never reaches zero.
- **Energy** 0–100. Falls a little per interaction, recovers ~10 an hour.

**Derive, do not tick.** Store `happiness_at`, the value, and the timestamp; work
out the current value on read. A cron job that decays every creature every hour is
a thing that can fail silently, and a creature whose mood depends on whether a
scheduled task ran is a bug factory. The existing `GrowthCalculator` already works
this way — same pattern, copied.

Migration adds: `happiness_value`, `happiness_at`, `energy_value`, `energy_at`.

### Step 2 — The sidebar card

Your favourite creature (the one already used as the sidebar keepsake), plus:

- its picture, name and mood in words
- two bars, each labelled in text as well as colour (golden rule 6)
- **Pet** and **Feed** buttons, and a link to its full page

Mobile: below the personal links. Desktop: where the keepsake card is now.
Both buttons are real forms with CSRF, following the existing pet form — no
JavaScript required for them to work; JS only makes the update happen without a
page reload.

### Step 3 — Treats

A new item category. Treats drop from exploration (~10% a click) and are sold in
the shop for gems.

- Honey Treat — common — happiness +10, energy +20
- Mushroom Treat — uncommon — happiness +20
- Something restful — uncommon — energy +50

`POST /creature/{id}/feed` — owner only, item must be in inventory, consumed
through `InventoryRepository::removeOne()` (which is already safe against being
paid twice; read its comment before touching it).

### Step 4 — Creatures in the shop

Species become purchasable for gems: starters around 50, rarer ones 200–500.
Daily adoption goes away; exploration stays as the free way to find one.

Needs `shop_creatures` (species_id, gem_price), mirroring `shop_items`.

**Sequence deliberately last**, because it depends on gems having somewhere to
come from (step 0, done) and somewhere better to go (steps 1–3). Shipping it
first would make the shop the only thing gems are for, which is a worse game.

---

## Open questions for Skerro

1. **Does the mood need art?** A sleepy version of each creature would carry the
   whole feature, but it is one more drawing per species. Words and a face work
   without it.
2. **Should a creature react to treats it "likes"?** Cheap to add (one column,
   one line of copy) and it is the difference between feeding a stat and feeding
   a character.
3. ~~**What happens to the daily adoption people already use?**~~ **Answered:**
   retired outright. Keeping it alongside buying would have meant two ways to get
   a creature, which is worse than one — and the free route survives, because
   exploring still turns one up. `/adopt` redirects to the shop rather than
   404ing, so nobody's bookmark breaks.

For the two questions still open: the site works and looks finished without
either. A dozing creature currently reads as dozing through its words, its energy
bar, and a slightly softened sprite — a real sleepy drawing would be nicer, not
necessary.

---

## Definition of done for M2

- Every stat rule has a test proving the floor holds and absence is not punished
- `php bin/smoke-test.php` opens the sidebar card, feeds a treat, buys a creature
- The card is checked at 360px and at 1280px, by eye, before handover
- `prefers-reduced-motion` turns the heart particle off and the site stays warm
- Nothing in the mechanic can be farmed by a second account
