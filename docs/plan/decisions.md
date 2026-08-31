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

---

## 2026-08-13 — Three usability decisions (the finder, the purse, the moments)

The full plan, with the security answers and test cases, is in
`docs/plan/2026-08-13-usability-pass.md`. The decisions in short:

### 1. The creature's line became a rare event, not a header fixture

**Decided.** A creature's line used to sit in the header of every page, changing
once a day. Always-there is the same as never-noticed. Now roughly one page in
five (tunable: `gameplay.creature_moments.chance_percent`) shows a speech bubble
at the top of the content — the creature's portrait and its line — and ANY owned
creature may speak, not just the featured one.

**Left out on purpose: any reward for seeing a moment.** The instant a moment
pays coins or XP, page loads become farmable by alt accounts. It stays flavour.

### 2. The purse lives in the header, as status — not a button

**Decided.** "Can I afford this?" is asked at the top of a page, so the balance
shows at the top of every page: a dark status chip, centred above the nav on
phones, tucked in the frame's top-right corner from tablet width up.

**Left out on purpose: making the chip a link.** An earlier version of the purse
sat among the nav pills, looked pressable, and did nothing — status dressed as a
control is a small lie. The shop restates the balance next to the prices anyway.

### 3. Finding things: category pills + a name search, one pattern for both economy pages

**Decided.** Inventory and shop share one "finder row" (`ItemFinder` +
`partials/item-finder.php`): category pill links with icon, word and count, and
a GET search box that appears once a list holds 9+ things (tunable:
`gameplay.finder.search_shown_from`). Filters combine, always say
"Showing X of Y", and a no-hit result always links back to everything.

**Left out on purpose: sort controls, pagination, and live-as-you-type
filtering.** Category + search covers the next milestones' volumes; each of the
three left-outs is real complexity that should wait until a real list hurts.
The finder's URL shape (?category=&q=) is what a bigger SQL-backed version
would keep, so nothing built against it will need to change.

---

## 2026-08-13 (later the same day) — The finder filters without reloading

**Decided.** The Product Owner tried the finder and named the cost of the
GET-round-trip honestly: every pill click reloads the whole page, and the reload
(frame animation and all) reads as a loading screen in the middle of play. So
the finder gained the site's first JavaScript, `public/js/item-finder.js` —
when the full list is already in the page, pills and the search box now filter
the cards in place: no request, no reload, and the search filters as you type.

This revisits the earlier "left out on purpose: live filtering" — left out then
because nothing had shown it was needed; added now because playing with it did.
That is the order these decisions are supposed to happen in.

**What kept it honest:**

- **It is an enhancement, not a requirement.** The pills stay real links and
  the search a real GET form. With JavaScript off (or broken, or still
  loading), everything works exactly as before — the server-side filtering and
  its tests are untouched.
- **The script only takes over when it can tell the truth.** The server marks
  the page with `data-complete-list` only when the whole unfiltered list is in
  the DOM. A bookmarked filtered URL renders only a slice, so there the links
  navigate normally instead of pretending to filter.
- **The address bar stays honest.** After each in-place change the URL is
  updated to the same `?category=&q=` shape the server understands, so any
  filtered view can still be bookmarked and shared.

**Left out on purpose: doing the same for the Buy button.** Buying moves money,
and the POST → redirect → confirmation flow is the boring safe pattern for
that. Making purchases fire without a reload means CSRF, double-submit and
failure handling in JavaScript — real complexity against a much smaller
annoyance, since buying is a decision, not a browsing gesture. Raise it
separately if the reload after Buy starts to hurt.

---

## 2026-08-14 — The artist's layout: sidebar, banner, and a starry sky

The full plan is in `docs/plan/2026-08-14-the-new-face.md`. The artist drew a
new shape for the whole site — a parchment sidebar beside a wide main panel,
floating on her hand-painted starfield tile — and left three questions open.
The decisions in short:

### 1. The sidebar is *you*; the bar under the banner is *the world*

**Decided.** Sidebar: name, purse, avatar (which is the door to your own page),
home / my creatures / inventory, log out, and your favourite creature as a
keepsake card. Under the banner: shop · explore · adopt · browse creatures ·
find people. Five buttons each side of the split, every button somewhere for a
reason, and the old grouped nine-pill nav (labels and all) retires.

### 2. Phones get the banner, not the badge — and their height back

**Decided.** The wide painted banner is the masthead everywhere; at phone width
it is a ~67px strip instead of the old 208px square badge filling the screen on
every page. The sidebar renders first as a compact identity strip, the
favourite-creature card is desktop-only (it lives on home and your page
anyway), and no sticky bars eat the space saved.

### 3. The creature moment becomes the popup the artist drew — on desktop

**Decided.** From 900px up, the moment overlays the banner from the top left,
its portrait seated on a parchment circle so it reads against the busy art.
The banner is decoration, so nothing important is covered. On phones it stays
in the flow above the content. Same markup, CSS only.

### The palette moved one token

`--purple` went from `#6A4C93` to `#7B3FA0` (orchid), to rhyme with the
magenta nebula clouds. Chosen by arithmetic before taste: 5.43:1 on parchment
against the old value's 5.42:1, so every AA pass still passes. The plum
gradient background is gone; the body is the tiled painting over a new
`--night` fallback. CLAUDE.md's palette section was amended in the same change.

**Left out on purpose:** faq/tos/credits footer links (the pages don't exist —
a dead link is worse than no link); any hamburger or collapsing menu; a second
mobile rendering of the favourite creature.

---

## 2026-08-20 — The milestone that never was, and the adoption question

**What happened.** After M1 shipped, a session invented a milestone called
"M2 — the creature you keep" and built it. The agreed plan
(`felkyo-milestones.md`, mirrored in `felkyo-build-plan-2.md`) has no such
milestone — its M2 is **the creator's panel**, the admin and artist hub. The
invented milestone pulled work forward from M6.2 (petting pays the petter),
M8.1 (care) and M8.6 (play), and added things no milestone contains: the
keepsake card, the favourite star, and creatures sold for gems.

**Decided.** The work stays — it is good, tested, and the pulled-forward
economy carries the safety guards M6.2 demands. But the record now calls it
what it is: an **interlude after M1**, not M2. The plan document, the
developer guide and the build plan were all corrected on this date, and the
build plan carries "already built" notes at M6.2, M8.1 and M8.6 so nothing is
built twice. The next milestone is the plan's real M2, the creator's panel.

**Why this entry exists.** A future reader comparing the plan against the
shipped features would find a milestone that matches nothing and reasonably
conclude the plan was stale. It was not — the label was wrong, and this is
the correction trail.

**Left open, for the Product Owner:**

- **Daily adoption was retired** in favour of buying creatures with gems.
  That was a real design decision taken without being in any plan. Skerro
  should confirm he wants it this way — or the adoption day returns.
- **M8.6's scope discussion is still owed.** Three fun-only mini-games exist;
  whether play should ever reward anything tangible has not been decided.

---

## 2026-08-20 — M2.1 signed off: roles, the door, and a second factor

The full plan, with the three security questions answered, is
`docs/plan/2026-08-20-m2-1-admin-foundation.md`. The Lead Developer signed
off the same day on all three open points:

- **The plan as written** — join-table roles, the AdminGate chokepoint every
  /admin route is registered through, an append-only audit log, and a CLI
  bootstrap (`bin/grant-role.php`) as the only way the first owner can exist.
- **Second factor: yes, in M2.1.** TOTP in vanilla PHP (~80 lines, pinned to
  the RFC 6238 vectors by tests, no new dependency), required for every
  role-holder, enrolled at first panel entry, with hashed one-time recovery
  codes. Enrolment shows the secret as text — no QR code, because a QR
  library is a dependency this does not need; if real staff find typing the
  secret annoying, adding one is a separate ask-first decision.
- **The numbers**: panel-door confirmation stales after 30 idle minutes;
  5 door attempts per 15 minutes per user+IP; 10 role changes per hour per
  actor. All named config values under `security` in `config/config.php`.

Two things decided along the way, worth their own line:

- **CSRF tokens now rotate at login** (for everyone, not just staff). The
  token used to live unchanged for the whole session, including across
  authentication; a token captured pre-login must not survive it.
- **The guarantee test learned about SET NULL.** "Everything pointing at
  users CASCADEs on delete" was too strict for `granted_by_user_id`, where
  CASCADE would delete the TARGET's role because the GRANTER left. Columns
  meaning "that user once did this to somebody else's row" SET NULL instead —
  both rules remove every reference to a deleted account, which is what the
  data-protection promise actually requires.

**Still ahead and unchanged:** M2.2 (image upload) is a MAJOR DECISION —
hosting persistence must be verified and options presented for approval
before any code. The two Skerro questions (adoption retirement, mini-game
rewards) remain parked and untouched.

---

## 2026-08-21 — M2.2: the pictures live on a Railway volume

The memo with the verification and the three options, written for readers
new to hosting, is `docs/plan/2026-08-21-m2-2-where-files-live.md`.

**Decided (Lead Developer, 2026-08-21):**

- **Option A — a Railway volume.** A persistent disk on the app service,
  cents a month at this size, backups a switch in the dashboard. Chosen
  over Cloudflare R2 because R2's proper public serving needs a domain
  Felkyo does not own yet plus an account, a key and signing code — too
  many new places for a Tuesday-night breakage to hide, for a closed demo
  run by two people new to hosting. Storage sits behind one `ImageStore`
  class so R2 is a swap, not a rewrite, when a domain arrives (revisit at
  the open-registration gate).
- **She sets up the volume and its backups herself**, from exact steps
  written into the deployment guide.
- **Limits: 5 MB per file, 2048×2048 pixels, GIF/PNG/WebP.** The artist's
  current pictures are 515–630 KB; there will be more and larger.
- **Animated files are stored byte-for-byte** (re-encoding would flatten
  them, which CLAUDE.md forbids). The risks that leaves and what the
  artist must do at export time are in the M2.2 build plan.

**The verification changed the recommendation.** The first instinct was
R2; the custom-domain requirement (its built-in address is documented as
"development only, rate-limited") is what tipped it. Recorded so a future
reader does not wonder why the "obviously more robust" option was passed
over.

---

## 2026-08-31 — The Lead Developer's ask: whole pages editable, not just pieces

**What was asked.** After using the panel on production, Katharina asked for
more substance behind it: the artist should be able to set up a SHOP in one
sitting (background image, wares, shopkeeper and their lines, publish) and
an EXPLORATION area likewise — instead of iterating through the Lead
Developer and a coding session for every change.

**The answer, sorted honestly into two piles:**

- **Already the plan.** A one-sitting shop editor is M2.4 verbatim ("NPC
  art, dialogue lines, offerings"), and M2.4 will also grow the small
  frontend surfaces those settings feed (the shop page showing its
  background, portrait and dialogue) — a panel screen and the frontend
  spot it fills ship together, per principle (b2). Area backgrounds and
  the bulk loot-table editor are M2.4/M2.5.
- **A new question, parked for Skerro.** "Clickable points placed on the
  exploration image" as a PANEL-EDITED thing is not a quick add-on.
  *(Corrected 2026-08-31: the first version of this entry claimed no
  positional hotspots exist — wrong. The exploration config DOES place
  clickable spots by x/y percentage on each area's scene.)* What does not
  exist is any way to EDIT them, or areas at all, outside config — and the
  area editor belongs with M2.5's bulk loot tables and the M9+ engine work
  (foraging/fishing/mining/hunting as one engine), where "what is a spot"
  gets designed once for all four activities. Parked beside the two
  existing Skerro questions (adoption retirement, minigame rewards) so it
  is decided by design, not slipped in as a panel feature.

**Order unchanged:** M2.3 (one page per creature) stays next — M2's
definition of done hangs on it — then M2.4 brings the shop editor.

---

## 2026-08-31 — M2.3 signed off, and alternate forms wait for the library

The build plan (`docs/plan/2026-08-31-m2-3-build-plan.md`) was signed off
as written, with all four open questions decided as recommended:

- **Alternate forms are DEFERRED to M5 (the library)** — written down so it
  reads as chosen, not forgotten. No game mechanic assigns or displays a
  form today; building their storage and UI now would put dead weight on
  the panel's most important screen. M5 is where forms first appear, and a
  `form` column on `species_stages` is one small migration then. What a
  form even IS (a colour? a rare variant? seasonal?) is Skerro's design
  call to make at that point.
- **Publish minimum**: every stage has a sprite, plus name and flavour
  text. Blurbs, lore and library art stay optional until the library
  exists.
- **Versions**: the newest 20 per species are kept.
- **Unpublish is allowed**: existing creatures keep living and rendering;
  the species leaves the shop and starter pool. The button says so in
  plain words.

---

## 2026-08-31 — M2.4 re-scoped: two editors now, two moved to their homes

Signed off with the M2.4 build plan
(`docs/plan/2026-08-31-m2-4-build-plan.md`). Written so Skerro can read
this entry alone and know what changed and why.

**What M2.4 now builds:** the ITEM editor and the SHOP editor — including
the shop page growing its background, its keeper (name, portrait, greeting,
and the "we don't have that" line, all editable), and seasonal from/to
dates on shelf listings (the columns decided 2026-08-12 finally come to
life).

**What moved, and where to:**

- **The postcard editor moved to M6.** M6 already defines what a postcard
  IS — an item carrying a fixed pre-authored message. Building its editor
  now would mean inventing the postcard's data shape ahead of that design
  and likely reworking it. Principle (b2) — a content type ships WITH its
  panel screen — is kept, not broken: both arrive together in M6.
- **The theme editor moved to M4.** M4's design requires contrast checked
  automatically while colours are picked; a colour editor without that
  guard is the exact tool that ships an unreadable page. Editor and
  content type arrive together in M4, guard included.
- **Item categories stay a manual recipe until M4.** Making them
  panel-editable means moving colours and icons from files into data —
  the very machinery the theme editor builds. Eight categories exist,
  stable, seeded from the design document; the written recipe covers the
  rare change until then.

**Also decided:** one shared `content_versions` table becomes the undo for
every editor (species history migrates in — one engine, not three copies);
and items/shops get the species rules — slugs minted once and never
editable, no deletion, retiring = back to draft — because starter gifts
and exploration loot tables reference items by slug, and a rename would
break them silently.

---

## 2026-08-31 — M2.5 signed off: areas become data, spots become clickable to place

The build plan (`docs/plan/2026-08-31-m2-5-build-plan.md`) was signed off:
exploration areas move from config into tables (whispering-wood migrated
verbatim, the config block removed — one source of truth; the mechanics
knobs stay config), the loot table is edited as paste-friendly plain lines
(all-or-nothing with per-line errors and nothing typed ever lost), loot
references items by real foreign key (a typo becomes unwritable, not
silently broken), and the publish minimum is a name, one spot, one loot
row — the background stays optional over the gradient fallback.

**One deviation from the recommendation, chosen deliberately:**
click-on-the-image spot placement ships in M2.5 itself rather than later.
The shape honours the standing accessibility rule (recorded 2026-08-12 for
M11 decorating, applied here): the number fields REMAIN the full, keyboard-
reachable, no-JavaScript way; clicking the scene is a vanilla-JS
convenience layered on top, never the only way to place anything.
