# Usability pass — creature moments, the visible purse, and finding things

*Planned 2026-08-13, before any code. Three changes, each looked at through three
pairs of eyes: gameplay (does it make the game better?), GUI (does it look like it
belongs?), and usability (can everyone actually use it?). Security questions and
test cases are answered here first, as CLAUDE.md section 6 requires.*

---

## 1. Creature moments — the voice becomes an event

**The problem.** A creature's line sat in the header of every page, in the same
spot, changing once a day. Anything that is always there becomes wallpaper —
after three visits nobody reads it. And the creature saying it was invisible:
a voice with no face.

**Gameplay view.** Made occasional, the line turns into a small found moment:
every few clicks, one of your creatures pops up and *does something*. Rarity is
what makes it feel alive rather than administrative. Two deliberate limits:

- **No reward attached.** The moment is pure flavour. The instant it pays coins
  or XP, players (and their alt accounts) would farm page loads for it.
- **Any owned creature can speak, not only the featured one.** A player with four
  creatures should occasionally hear from the quiet ones — that is what makes a
  flock feel like a flock.

**GUI view.** A speech bubble at the top of the page content: the creature's
portrait (pixel-crisp, its actual growth stage) beside its line in the display
italic, with a bubble tail pointing at the portrait. It arrives with the page —
no JavaScript, no popup, no overlay covering content. Gentle fade-in, killed by
the global reduced-motion rule.

**Usability view.** The whole bubble is one link to that creature's page (a
moment invites a visit; one big ≥44px target, clear focus ring). The portrait
carries an empty `alt` because the creature's name is already in the line —
a screen reader hears the sentence once, not twice. It renders above any page
flash, but flashes stay directly visible below it, and the bubble is visually
unmistakable as flavour, not feedback.

**How it works.**
- Config gains `gameplay.creature_moments` = `['chance_percent' => 20, 'lines' => [...]]`
  (the lines move in from `creature_greetings`, which goes away). ~1 page in 5;
  the artist can retune both without a developer.
- New `Felkyo\Creatures\CreatureMoments` service replaces `CreatureGreeting`:
  `maybeFor(array $summaries): ?array` rolls the chance, picks a random creature
  and a random line. Randomness is injectable (a `random_int`-shaped callable)
  so tests are deterministic.
- `public/index.php` computes the moment once per request for logged-in players
  and shares it with the layout; the layout inserts a new
  `partials/creature-moment.php`. The header keeps the guest tagline only.

**Security.** No user input arrives. Creature names are player text and are
escaped by Plates on output, as everywhere. No reward → nothing to farm.

**Test cases** (`tests/Unit/CreatureMomentsTest.php`, plus a render test):
1. chance 0 → never a moment, even with creatures.
2. chance 100 → always a moment when creatures exist.
3. no creatures → null even at chance 100.
4. `{name}` in the line is replaced with the speaking creature's name.
5. with a scripted random source, the expected creature and line are picked
   (proves both the creature and the line vary, not just the line).
6. the partial renders portrait `img` (correct stage path), the line, and a link
   to the creature's page; a hostile creature name arrives escaped.

---

## 2. The purse moves into the header

**The problem.** Your coin balance lived at the bottom of the page, below the
fold on every screen. The question "can I afford this?" is asked at the top.

**GUI view.** A small chip — gold diamond + "52 coins" — in the site's mono
status voice. On phones it sits centred just above the navigation; from 640px it
tucks into the top-right corner of the parchment frame, where a wide screen has
room to spare. Dark plum chip with light text: the same treatment as the
spotlight label, well past AA contrast.

**Usability view.** It is deliberately **not** a link and does not look like
one — an earlier iteration had it pill-shaped among the nav buttons, where it
looked pressable and did nothing. A status chip reads as status: flat, quiet,
labelled in words ("You're carrying … coins" for screen readers). The shop page
keeps its own purse line beside the prices, where the deciding happens.

**Consequence.** The footer strip slims to "signed in as … · log out".

**Security.** Displays the session player's own balance; no input.

**Test cases** (extend existing integration tests):
1. a logged-in page (e.g. /creatures) contains the header purse with the real
   balance;
2. a guest page contains no purse chip.

---

## 3. Finding things — search and category filters for inventory and shop

**The problem.** Both pages are a wall of cards. Fine at 4 items; unusable at 30.
Later milestones (more items, bigger shops) make this worse, so the pattern
must be built once, now, and scale by data.

**Usability view — the "finder row", one pattern for both pages:**
- **Category pills** (links, never a dropdown — CLAUDE.md section 8): "everything"
  plus one pill per category *present in the current list*, each with its icon,
  its name in words, and a count ("dish · 2"). The active pill is filled and
  marked `aria-current="true"`; pills keep any active search in their URLs, so
  the two filters combine instead of resetting each other.
- **A search field** (GET form, plain HTML, works without JavaScript): filters by
  name, case-insensitive substring. Shown when the list is big enough to need it
  (9+ piles/items) or when a search is already active (so the state is always
  editable) — a search box floating over four items is clutter, not help.
- **Say what happened** (golden rule 4): an active filter always shows
  "Showing 3 of 12". No hits is never a dead end: a friendly line plus a
  "show everything" link.
- Pills are ≥44px tap targets; the whole row wraps on a phone.

**Gameplay/architecture view.** Filtering happens in PHP over the already-fetched
list (`Felkyo\Economy\ItemFinder`, shared by both pages). The lists are small and
per-player; no SQL changes, no new query surface, and the repository contracts
stay untouched. When a later milestone brings hundreds of items, the finder's
*interface* (category + q in the URL) stays identical and only its internals
would move into SQL — pages and links built against it will not change.

**Behaviour matrix (inventory):**
- no filter, no search → grouped by category with headings (browsing view).
- any filter/search active → one flat grid plus the "Showing X of Y" line.

**Security — the three mandatory questions:**
1. **Who is allowed, and how is it enforced?** Inventory: the controller requires
   a logged-in session and only ever queries `findForUser($sessionUserId)` — the
   filter runs over a list that is already scoped to the owner, so no URL
   parameter can reach another player's things. Shop: any logged-in player;
   the catalogue is public data by design.
2. **Worst a malicious user can do?** Send a hostile `q` → trimmed, capped at 40
   characters, matched in PHP (never concatenated into SQL — there is no SQL),
   escaped by Plates on output. Send an unknown/hostile `category` → validated
   against the slugs actually present; anything else silently means "everything".
   Hammer the endpoints → read-only GETs over their own data / a public
   catalogue; unlike `/players` (which searches *other people* and keeps its
   rate limit against enumeration), there is nothing here to enumerate.
3. **What do the tests prove?** See below — including that another player's
   items never appear under any filter, and that hostile input comes out inert.

**Test cases:**
- Unit (`tests/Unit/ItemFinderTest.php`):
  1. empty q and category "all" → everything comes back;
  2. q matches case-insensitively, substring anywhere in the name;
  3. category keeps only that category;
  4. q and category combine (AND);
  5. unknown category → treated as "everything";
  6. q of only whitespace → no filtering;
  7. q longer than 40 characters is truncated before matching (no error).
- Integration (`InventoryControllerTest`):
  8. `?category=<slug>` shows only that category's stacks and the count line;
  9. `?q=` matching one item shows exactly it;
  10. `?q=` with no hits shows the no-hits line and a "show everything" link;
  11. a second user's items never appear, with and without filters;
  12. `q` containing HTML arrives escaped in the page.
- Integration (`ShopControllerTest`):
  13. `?category=` filters the stock; 14. no-hit search shows the reset link.

---

## Left out on purpose

- **No sort controls.** Category + name search covers today's and next
  milestone's volumes; sorting is a third control fighting for the same row.
  Add it when a real list proves too long to scan sorted-by-category.
- **No pagination anywhere yet.** Browse already caps by config; inventory and
  shop are filtered, not paged. Revisit when a page provably gets slow.
- **No JS-live filtering.** The GET round-trip is instant at this scale, works
  with every keyboard and screen reader, and keeps the URL shareable. HTMX can
  progressively enhance the same endpoints later without changing them.
- **The `_incoming` shop art stays unwired** — it is watermarked; wiring it is a
  content decision for the artist, not a polish decision.
