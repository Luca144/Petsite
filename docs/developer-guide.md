# Felkyo Creatures — Developer Guide

This guide grows with the project. Each increment adds a short section explaining
**what it added** and **how it works**, written for someone still learning to
code. If you are taking this project over, read it start to finish — it is the
map of how everything fits together.

For how to *run* the project, see [setup-guide.md](setup-guide.md).
For the database, see [schema.md](schema.md).

---

## The shape of the project

```
public/        The web root. index.php is the ONLY entry point (front controller).
               Also holds assets (creature art, CSS) the browser downloads.
src/           Our PHP classes, grouped by area. Autoloaded via PSR-4 (Felkyo\).
templates/     Plates templates — the HTML, kept separate from the logic.
config/        Start-up (bootstrap.php) and all settings/knobs (config.php).
migrations/    Database structure changes, managed by Phinx (from increment 0.2).
tests/         Automated tests (PHPUnit).
docs/          These guides.
logs/          Runtime log files (not committed to Git).
vendor/        Installed libraries (not committed; restored by Composer).
```

**The layered design (why files are split the way they are).** We deliberately
keep three jobs separate so each can change without breaking the others:

- **Controllers / route handlers** decide *what to do* for a request — who is
  allowed, then hand off. Example: `GuestbookController`.
- **Services** hold *game rules*. Example: `GuestbookService` decides whether a
  signing is valid; `PettingService` decides what a pet does.
- **Repositories** own *database access* — all the SQL, and nothing else.
  Example: `GuestbookRepository`.

Controllers never talk to the database directly. This boundary is a rule
(CLAUDE.md section 5), and the reason is that it keeps each piece small and
replaceable.

---

## Increment 0.1 — Project skeleton

**What it delivers:** a working foundation where a single "hello" page renders
through the whole stack, plus a passing test suite. No game features yet — this
is the frame everything else hangs on.

**How a request flows (read this once and the rest makes sense):**

1. The web server sends *every* URL to `public/index.php` — the **front
   controller**. This is the single place a request begins.
2. `index.php` runs `config/bootstrap.php`, which loads Composer's autoloader
   and the `.env` file, then returns the settings from `config/config.php`.
3. `index.php` builds the tools (a `Router`, the Plates template `Engine`, a
   `FileLogger`) and registers the routes — currently just `GET /`.
4. The **`Router`** ([src/Http/Router.php](../src/Http/Router.php)) looks at the
   requested method and path and runs the matching handler. The handler renders
   a template to HTML and returns it; the router sends it to the browser. An
   unknown address gets a plain 404 (the pretty 404 comes in increment C.2).
5. **Plates** turns `templates/pages/hello.php` (wrapped in
   `templates/layout.php`) into the final HTML. Templates auto-escape values, so
   user text can't break the page or inject scripts.

**The pieces added in 0.1:**

| File | What it is |
| --- | --- |
| `config/bootstrap.php` | Shared start-up: autoloader + `.env`, returns config. |
| `config/config.php` | All settings in one array; the home for future "knobs". |
| `src/Http/Router.php` | Our small, hand-written URL router. |
| `src/Core/Database.php` | Factory that builds a safely-configured PDO connection. |
| `src/Core/FileLogger.php` | Minimal logger that appends lines to `logs/app.log`. |
| `public/index.php` | The front controller and the route list. |
| `templates/layout.php`, `templates/pages/hello.php` | The bare page and the hello page. |
| `tests/` + `phpunit.xml` | The test harness, pointed at the separate `felkyo_test` DB. |

**Two decisions worth knowing:**

- **Router and logger are hand-written, not libraries.** For a site this size
  they are a small amount of clear code, which CLAUDE.md prefers over extra
  dependencies. If routes ever need an id in the address (e.g. `/creature/42`),
  the `Router` is the one class to extend — that is the intended place.
- **The tests use a separate database.** `tests/bootstrap.php` forces the
  database name to `felkyo_test` before any test runs, so tests can never damage
  real data. Do not remove that guard.

**How to add a new page (the copyable recipe):**

1. Create a template under `templates/pages/`, e.g. `about.php`, and have it call
   `$this->layout('layout', ['title' => '...'])` like `hello.php` does.
2. In `public/index.php`, register a route:
   `$router->get('/about', function () use ($templates) { return $templates->render('pages/about'); });`
3. Add a test for it under `tests/`.

---

## Increment 0.2 — Database schema

**What it delivers:** the whole database structure for the entire project,
created by migrations, plus documentation and tests that prove it. No feature
logic yet — this is the shape the data will live in.

**Migrations (how the database is built).** The database is never edited by hand.
Instead, each structural change is a small script in `migrations/`, run by
**Phinx**. The scripts are numbered and run in order. This means anyone can build
an identical database from nothing by running the migrations, and every change is
recorded in Git. Configuration for Phinx is in `phinx.php` at the project root; it
reads the same `.env` credentials the app uses, and defines three environments:
`development` (your `felkyo`), `testing` (`felkyo_test`), and `production` (Phase D).

**The tables** are described in plain language in [schema.md](schema.md). The
short version: `users` own `creatures` (each of a `species`); `pettings` logs each
pet; `exploration_visits` tracks click limits; `items` + `inventory` + `shops` +
`shop_items` are the economy foundation; `rate_limit_hits` backs the rate limiter.

**Two decisions worth knowing:**

- **Growth is derived, not stored.** Creatures store only `xp`. Level and life
  stage are calculated from `xp`, so they can never drift out of sync.
- **`pettings` is an event log, not a counter.** Recording each pet (who, which
  creature, when) is what lets us enforce a per-person cooldown and an anti-spam
  currency cap later — a single "last petted" timestamp could not.

**Tests migrate the test database for you.** `tests/bootstrap.php` runs the Phinx
migrations against `felkyo_test` before any test, so the suite is self-contained —
you never have to remember to migrate the test database. The integration test in
`tests/Integration/SchemaTest.php` then checks the tables, required-column rules,
and a foreign key really exist. Run the suite with:
`C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit`.

**How to change the schema later (the recipe):** see the end of
[schema.md](schema.md) — create a new migration, write the change, migrate both
environments, update the docs. Never edit an already-applied migration or change
tables by hand.

---

## Increment 0.3 — Design language and base layout

**What it delivers:** the site's visual foundation — a central theme, a
mobile-first base layout, the web fonts, and a small reusable component kit — so
every later page inherits the cosy-autumn look instead of re-inventing it. The
welcome page now renders inside the real themed layout.

**The look:** "cosy autumn hygge" — a warm lamplit-evening plum background with a
gold-edged parchment frame floating on it, the Fraunces wordmark, Space Mono
labels, and a few restrained sparkles. It follows the palette and fonts fixed in
CLAUDE.md exactly.

**Where the styles live (split by concern, all in `public/css/`):**

| File | What it holds |
| --- | --- |
| `theme.css` | The design **tokens** — every colour, font, spacing, radius and shadow as a CSS custom property (`--name`). The single place to retune the look. Also the reset, base typography, focus styles, and the global reduced-motion rule. |
| `layout.css` | The shared page **structure** — skip link, the parchment frame, masthead/wordmark, nav pills, divider, footer, ambient sparkles. |
| `components.css` | The reusable **components** — buttons (`.btn`, `.btn--primary`, `.btn--secondary`), cards (`.card`, `.card--dark`), tiles (`.tile-grid`, `.tile`), form fields (`.field*`), and badges. |

**The golden rules this increment sets (follow them on every later page):**

- **Components never hardcode a colour.** They only use the `--tokens` from
  `theme.css`. Need a new colour? Add a named token to `theme.css` first. (There
  is zero raw hex in `layout.css`/`components.css` — keep it that way.)
- **No dropdowns, ever.** For choices, use `.tile-grid` tiles or a button group,
  never a `<select>`. A test (`LayoutRenderTest`) fails if a `<select>` appears.
- **Mobile-first.** Base styles target a ~360px phone; desktop tweaks live in
  `@media (min-width: …)` blocks at the bottom of a file.
- **Accessible by default.** Real landmarks (`<header>`/`<main>`/`<footer>`/`<nav>`),
  a skip link, visible keyboard focus, ≥44px touch targets, gold used only on dark
  or as decoration (never as readable text on parchment), and full
  `prefers-reduced-motion` support (all motion off, sparkles hidden).

**How to build a new page (the recipe):** create `templates/pages/yourpage.php`,
call `$this->layout('layout', ['title' => '…'])` at the top, then write the body
using the components above (a `.card` to group things, `.btn--primary` for the
main action, `.tile-grid` for choices). Register its route in `public/index.php`
and add a test. The themed shell, fonts and styles come for free.

**Note on the logo:** at this point the masthead was a typographic stand-in set in
Fraunces. It was replaced by the artist's real logo art later — see the
"Art import" section below.

---

## Increment A.1 — Accounts

**What it delivers:** people can register, log in, and log out. Passwords are
hashed, input is validated, forms are CSRF-protected, and login/registration are
rate-limited against abuse.

**The request lifecycle now (read once, it explains the whole app).** A request
flows through small, single-purpose pieces:

```
Browser → public/index.php (front controller)
        → Request (a snapshot of the request)
        → Router (picks the handler for the method + path)
        → Controller (web glue: read input, call a service, return a Response)
        → Service (the business rules)         ┐ never touched by the controller
        → Repository (the SQL)                 ┘ directly — layers stay separate
        → Response (status + headers + body) → sent back to the browser
```

`Request` and `Response` are deliberately tiny value objects. Because a
controller **returns** a `Response` (instead of printing or redirecting itself),
tests build a `Request` by hand, dispatch it through the `Router`, and check the
returned `Response` — no web server needed. That is exactly how
`tests/Integration/AuthControllerTest.php` works.

**The pieces added, by layer:**

| Layer | Classes | Job |
| --- | --- | --- |
| HTTP | `Request`, `Response`, `Router`, `Csrf` | The web plumbing. |
| Controllers | `RegisterController`, `LoginController`, `LogoutController` | Read the form, call a service, return a page/redirect. |
| Services | `RegistrationService`, `Authenticator`, `PasswordHasher` | The account rules. |
| Repositories | `UserRepository`, `RateLimitRepository` | All the SQL (prepared statements). |
| Support | `Session`, `UserValidator`, `RateLimiter`, `User`, `RegistrationResult` | Session state, input rules, throttling, data shapes. |

**Security, and where each rule lives (CLAUDE.md section 6):**

- **Passwords** are hashed with `password_hash()` (default algorithm) — see
  `PasswordHasher`. The plain password is never stored or logged.
- **CSRF:** every form includes a hidden token via the `csrf_field()` template
  helper; every POST controller checks it with `Csrf::isValid()` before acting.
- **Validation** lives in `UserValidator` (a dedicated class), not in controllers.
- **Sessions** start with `HttpOnly`, `SameSite=Lax`, and (in production) `Secure`
  cookies, and the session id is regenerated on login (anti session-fixation).
- **Rate limits** (from config, per IP): login blocks after 5 failed attempts /
  15 min; registration caps 3 new accounts / hour. Login records only *failed*
  attempts; registration records only *successful* sign-ups.
- **No user enumeration:** a failed login shows one message whether the username
  or the password was wrong.

**Registration can be closed later (Phase D):** all sign-ups go through the one
`RegistrationService`/`RegisterController` path, so the future "registration off"
flag is a small, single-place change. It is intentionally not built yet.

**How to add a protected, state-changing page (the recipe):**

1. Write a repository method for any new SQL, and a service for any new rules.
2. Add a controller whose action reads the `Request`, checks `Csrf::isValid()`
   for POSTs, calls the service, and returns a `Response`.
3. To require login, check `$session->has('user_id')` at the top and redirect if
   not (the controllers here show the pattern).
4. Register the route in `public/index.php` and wire the controller's
   dependencies there.
5. Every form template must call `<?= $this->csrf_field() ?>`.
6. Add unit tests for the service rules and an integration test through the router.

**Auth settings** (rate limits, password/username length rules) live in the
`security` section of `config/config.php` — change them there, not in the code.

---

## Increments A.2 + A.3 — Creatures (ownership and the creature page)

**What they deliver:** a new player is given a starter creature on registration,
and every creature has its own page showing its animated portrait and state. This
is the heart of the game appearing for the first time.

**Species are content (data).** The kinds of creature live in the `species` table
and are seeded by a migration (`...seed_base_creature_species`). A species has a
`slug`, and its animated images are found by convention — **no image paths are
stored**:

```
public/assets/creatures/{slug}/{stage}.gif
e.g. public/assets/creatures/foxlen/baby.gif
```

To **add a species**: add a row (a new migration is the tidy way) with a unique
slug and name, and drop `baby.gif` / `juvenile.gif` / `adult.gif` into
`public/assets/creatures/{slug}/`. No code changes. (The three current names —
Foxlen, Mossling, Pebblewing — are placeholders paired with the provided sprites;
renaming them is just a data change.)

**Growth is derived, not stored.** A creature stores only `xp`. `GrowthCalculator`
turns that into a life stage (baby/juvenile/adult) using the thresholds in
`config.gameplay.stage_xp_thresholds`. The stage decides *which* image is shown
(`{stage}.gif`). Earning XP comes in B.2; for now every creature is a baby.

**The starter creature.** On a successful registration, `RegisterController` calls
`StarterCreatureService`, which picks a starter species and a friendly name (from
`config.gameplay.starter_creature_names`) — chosen from the user's id so it is
deterministic and testable — and creates the creature. A user owns *many*
creatures in the schema and queries from the start, even though they get one now.

**The creature page** (`/creature/{id}`) is the first page with a URL parameter.
`Router` now supports `{id}` placeholders (see its comments). `CreatureController`
loads the creature, its species and owner, computes the stage, and enforces **who
can see it**: a public creature is visible to anyone (even logged out); a private
one only to its owner — and a hidden creature returns the same 404 as a missing
one, so its existence is not revealed.

**Images:** shown with `<img class="… pixelated">` so pixel-art stays crisp when
scaled up (CLAUDE.md section 8); GIFs loop on their own. The sprite art is stored
via **Git LFS** (see `.gitattributes`).

**New pieces:**

| Layer | Classes / files |
| --- | --- |
| Domain | `Species`, `SpeciesRepository`, `Creature`, `CreatureRepository`, `GrowthCalculator` |
| Services | `StarterCreatureService` |
| Controllers | `HomeController` (welcome + your creatures), `CreatureController` |
| Templates | `pages/creature.php`, `pages/not-found.php`, updated `pages/hello.php` |
| Styles | `public/css/creature.css` |
| Data | species seed migration; the imported sprite art |

---

## Increments B.1 + B.2 — Petting and growth

**What they deliver:** the core loop. A logged-in visitor can **pet** a creature;
each pet raises its happiness and grants XP, gated by a **cooldown**; enough XP
raises its **level** and moves it through **life stages** (baby → juvenile →
adult), which swaps the animated sprite shown.

**The pet action** (`POST /creature/{id}/pet`, handled by `PetController`):

1. Must be logged in (else redirect to `/login`) and carry a valid CSRF token.
2. An IP rate limit caps mass-petting (`security.rate_limit_pet`); the real gate
   is the per-person, per-creature **cooldown** inside `PettingService`.
3. `PettingService` refuses if this person petted this creature within
   `gameplay.petting.cooldown_seconds`; otherwise it records the pet, and adds
   `happiness_per_pet` and `xp_per_pet` to the creature.
4. It redirects back to the creature page (Post/Redirect/Get, so a refresh does
   not pet again), carrying a one-time **flash** message (`Session::flash()`).

**Why petting is an event log.** Each pet is a row in `pettings` (who, which
creature, when). That is what lets the cooldown be *per person* ("have YOU petted
this recently?"), lets "times petted" be a count, and — later — lets currency be
earned when *others* pet your creature (B.7). The cooldown being per person means
two different players can each pet the same creature.

**Growth is all derived from XP.** `GrowthCalculator` turns XP into a level
(`levelFor`) and a stage (`stageFor`), using `gameplay.growth`
(`xp_per_level`, `stage_start_levels`). Nothing about level/stage is stored, so it
can never disagree with XP. The stage decides which sprite (`{stage}.gif`) the
page shows, so the creature visibly changes as it grows — no extra code, the page
just recomputes.

**Tuning the feel** is one place: `config/config.php` → `gameplay.growth` and
`gameplay.petting`. The defaults are deliberately quick (short cooldown, a level
per pet) so the loop is easy to try; raise them for a slower game.

**A small pattern introduced:** `CreatureProfileBuilder` gathers everything the
creature page needs (species, owner, level, stage, times petted) so the
controller stays thin and later pages that show creatures can reuse the assembly.

**New pieces:**

| Layer | Classes / files |
| --- | --- |
| Domain | `PettingRepository`, `PettingService`, `PettingResult`, `CreatureProfileBuilder`; `GrowthCalculator` (now level + stage); `CreatureRepository::applyPetting` |
| Controllers | `PetController`; `CreatureController` (now shows level/stats, pet button, flash) |
| Support | `Session::flash()` / `takeFlash()` |
| Templates/CSS | pet button + flash + stat panel in `pages/creature.php`; `.flash` styles |

> **A note on constructor size (CLAUDE.md §4).** Some controllers/services take
> more than four constructor arguments. Those arguments are *dependency wiring*
> (each becomes a typed, named property), not behavioural parameters — so we read
> §4's "max 4 parameters" as applying to behavioural functions, and keep each
> class focused instead (e.g. petting was split into `PetController` +
> `CreatureController`). Passing an untyped array-bag of dependencies would be
> less clear, not more. Flag this if you'd prefer a different approach.

---

## Increments B.3 + B.4 — Collection and daily adoption

**What they deliver:** a player can see all the creatures they own (the
collection), and adopt one new creature per day from the adoptable pool — so the
collection actually grows.

**B.3 — Collection** (`GET /creatures`, `CollectionController`). Lists the
logged-in player's creatures as cards. The one-to-many relationship was built in
from the start, so this is mostly a view. To avoid a database query per creature,
`CreatureProfileBuilder::summariesFor()` loads every species once and looks each
creature's up from that.

**B.4 — Daily adoption** (`GET`/`POST /adopt`, `AdoptionController` +
`AdoptionService`). Adoption is limited to once per "day":

- The limit is a **cooldown** measured from the user's `last_adopted_at`
  (`gameplay.adoption.cooldown_seconds`, default a full day). `UserRepository`
  gained `hasAdoptedWithin()` and `markAdopted()` for this.
- `AdoptionService` refuses if the player has adopted within the cooldown;
  otherwise it picks a **random** species from `SpeciesRepository::findAdoptable()`
  and a random name from `gameplay.creature_names`, creates the creature, and
  stamps `last_adopted_at`.
- The controller is a state-changing POST (login + CSRF + a light IP rate limit),
  and on success sends the player straight to their new creature's page.

**Config note:** the starter and adoption name pools were merged into one
`gameplay.creature_names`, used by both `StarterCreatureService` and
`AdoptionService`.

**New pieces:**

| Layer | Classes / files |
| --- | --- |
| Domain | `AdoptionService`, `AdoptionResult`; `SpeciesRepository::findAdoptable/all`; `CreatureProfileBuilder::summariesFor`; `UserRepository::hasAdoptedWithin/markAdopted` |
| Controllers | `CollectionController`, `AdoptionController` |
| Templates/CSS | `pages/collection.php`, `pages/adopt.php`; nav links; `.creature-collection` / `.creature-card` styles |

---

## Increment B.5 — Exploration

**What it delivers:** a themed area you can explore. It has clickable spots; each
click searches once, yielding a weighted-random reward (mostly nothing, sometimes
a new creature), and each visit allows a limited number of searches that refreshes
after a window. **This is the reusable template for all future areas.**

**Areas are data.** Every area lives in `config` under
`gameplay.exploration.areas` — its name, description, spot positions, and a
**loot table** of weighted rewards. One `ExplorationService` and one
`ExplorationController` drive them all, so **adding an area is a config entry (plus
a background image), not new code.**

**How a search works** (`POST /explore/{area}`):

1. Login + CSRF + a light IP rate limit.
2. `ExplorationService` checks the per-visit click limit. It asks
   `ExplorationRepository` how many clicks were used in the *current window*
   (a window counts only if it started within `window_seconds`); if there is no
   current window it starts a fresh one. At the limit, it returns "come back later".
3. Otherwise it rolls the loot table and spends a click.
4. If the reward is a creature, one is created for the player (random adoptable
   species + random name).

**Weighted randomness, made testable.** `WeightedPicker` does the maths, but the
**dice roll is passed in** to `pickByRoll()`. That keeps the selection logic pure
and exactly testable (a test hands it a specific roll); `ExplorationService`
generates the real `random_int` roll. This is the pattern to copy whenever
randomness needs testing.

**The visit window.** `exploration_visits` holds one row per (user, area) —
`clicks_used` and `window_started_at`. A migration made `(user_id, area_slug)`
unique so the row can be reset with a single "insert-or-update" statement. "Used
up your searches" persists until the window (from `window_started_at`) passes,
then the next search starts a fresh window.

**The scene** is one `<form>`; each spot is a submit button positioned by CSS
custom properties (`--x`/`--y`) set inline as data. The background is a themed
placeholder (`explore.css`) — real area art can replace it later.

**Adding a second area (the recipe):** add an entry under
`gameplay.exploration.areas` with a slug, name, description, `spots`, and a `loot`
table; drop in a background if you have one. Nothing else changes.

**New pieces:**

| Layer | Classes / files |
| --- | --- |
| Domain | `WeightedPicker`, `ExplorationRepository`, `ExplorationService`, `ExplorationResult` |
| Controllers | `ExplorationController` (index / show / search) |
| Templates/CSS | `pages/explore-index.php`, `pages/explore-area.php`; `explore.css`; nav link |
| Data | migration making a user's exploration visit unique per area |

---

## Increment B.6 — Seeing other people's creatures

**What it delivers:** a public **browse** page listing recently met creatures, so
players can discover and visit each other's creatures.

**Most of the visibility work was already in place.** The creature page (A.3)
already shows a public creature to anyone (even logged out) and hides a private
one from everyone but its owner. B.6 adds the discovery list on top:

- `CreatureRepository::findRecentPublic($limit)` returns the newest **public**
  creatures across all users (private ones are never included).
- `BrowseController` (`GET /browse`) is a **public** page — no login required.
  It reuses `CreatureProfileBuilder::summariesFor()` for the card data.
- The card markup was extracted into `partials/creature-card.php`, now shared by
  the collection and browse pages.
- "browse" is in the nav for **everyone** (logged in or not).

**New pieces:** `CreatureRepository::findRecentPublic`, `BrowseController`,
`pages/browse.php`, `partials/creature-card.php` (shared), nav link, and the
`gameplay.browse_recent_limit` knob.

---

## Increments B.7 + B.8 + B.9 — The economy foundation

**What they deliver:** a single currency you earn by being petted, an inventory of
what you own, and one shop to spend in. Deliberately minimal — no trading, no
player shops — but built data-driven so those are later extensions.

**B.7 — Currency.** A single currency lives as `users.currency_balance`. When
someone pets a creature they do **not** own, `PettingService` gives the **owner**
`gameplay.currency.per_pet` coins (`UserRepository::addCurrency`). Petting your own
creature earns nothing, and the **petting cooldown is what caps the earning** —
there is no separate anti-farm code. The balance shows as a chip in the header.

**B.8 — Inventory.** `inventory` links a user to an item with a quantity.
`InventoryRepository::findForUser` joins to `items` and returns whole `Item`
objects plus quantities; `InventoryController` groups them by the item's `type`,
so a **new item type appears as a new group with no code change**.

**B.9 — Shop.** One shop, seeded by migration along with a few items and the
`shop_items` links. The purchase flow is generic — *a shop has items, an item has
a price; buying validates the balance, deducts it, grants the item*:

- `PurchaseService::buy` gets the item's **real price from the database** via
  `ShopRepository::findSoldItem` (never trusts the browser), then in a
  **transaction** deducts and grants together.
- **A balance can never go negative:** `UserRepository::deductCurrency` subtracts
  only `WHERE currency_balance >= amount` and reports success by whether a row
  actually changed. If it fails, the transaction rolls back and nothing changes.

**Two gotchas worth remembering (both cost real bugs here):**

1. **Reused SQL placeholders.** With real prepared statements (emulation off), a
   named placeholder can't appear twice — `deductCurrency` needs `:amount` *and*
   `:minimum`, both bound to the same value. (An earlier `:amount`-twice version
   failed loudly, which is why the purchase tests exist.)
2. **Route vs. folder collisions.** The front controller only handles a URL if no
   real file/folder matches it first. An art folder at `public/shop/` will
   **shadow the `/shop` route** (on the dev server and on a production
   front-controller alike). Keep art under `public/assets/…`, never in a folder
   named like a route. (Phase D's server config must also route all non-file
   requests to `public/index.php`.)

**Adding a shop item (the recipe):** add a row to `items` (slug, name, price,
type), then a `shop_items` row linking it to a shop. It appears for sale and, once
owned, in the inventory under its type — no code change.

**New pieces:**

| Layer | Classes / files |
| --- | --- |
| Domain | `Item`, `Shop`, `InventoryRepository`, `ShopRepository`, `PurchaseService`, `PurchaseResult`; `UserRepository::addCurrency/deductCurrency`; `PettingService` now awards currency |
| Controllers | `InventoryController`, `ShopController` |
| Templates/CSS | `pages/inventory.php`, `pages/shop.php`; `economy.css`; nav links + balance chip |
| Data | migration seeding the shop, items, and their links |

---

## Increments C.1 + C.2 — Pet bio, and character & polish

**C.1 — Pet bio.** A creature's owner can write a bio; nobody else can.

- The creature page shows the bio, and — **only to the owner** — an edit form.
- `BioController` enforces ownership: even if a non-owner submits the form, it is
  refused (checked against the session's user id). It also needs login + CSRF and
  is IP rate-limited.
- `CreatureBioService` validates length (`gameplay.bio_max_length`) and runs the
  text through `ContentFilter`, a simple **whole-word, case-insensitive**
  blocked-word check (list in `config.moderation.blocked_words`). "scam" is caught
  but "scamper" is not. Then `CreatureRepository::updateBio` saves it.

**C.2 — Character & polish.**

- **Species flavour text:** a migration fills in a characterful line per species
  (placeholder content the PO can rewrite); it shows on the creature page.
- **Themed 404:** the `Router` now takes a `setNotFoundHandler`; the front
  controller registers one that renders the themed `pages/not-found.php` (a soft
  "nothing here but moonlight" page). Unmatched routes now get the real layout,
  not a plain string.
- General tidy and consistent empty states.

**New pieces:** `ContentFilter`, `CreatureBioService`, `BioResult`,
`CreatureRepository::updateBio`, `BioController`; `Router::setNotFoundHandler`;
species-flavour migration; `RouterTest` + `ContentFilterTest` (unit) and
bio-service / bio-controller tests. Config gained `gameplay.bio_max_length`,
`moderation.blocked_words`, and `security.rate_limit_bio`.

---

## Increment C.3 — The magic pass

**What it delivers:** a small, deliberate set of touches that make the site feel
alive — chosen for restraint, not spectacle.

- **Frame settle-in:** the content frame gently rises/fades in on each page load.
- **Tactile buttons:** a quick "press" on `:active` (on top of the hover lift).
- **Flash notices** slide in gently.
- **Creature portrait** lifts and glows a little more on hover.
- **Pet celebration:** right after a pet, a small heart floats up from the
  portrait — **once**. This uses a one-time `Session::celebrate('pet')` /
  `takeCelebration()` flag (like the flash): `PetController` sets it only on a real
  pet (not a cooldown), and `CreatureController` reads-and-clears it, adding a CSS
  class that plays the heart. No JavaScript — the heart is pure CSS.

**Reduced motion is respected throughout.** The global rule already disables
animations/transitions; crucially, every entrance animation's *resting* state is
"visible", so with motion disabled the content simply appears (never stuck
invisible), and the decorative heart is hidden entirely.

**Deliberately NOT added** (restraint): a level-up/stage-change animation would
need extra state tracking, and the sprite swap already marks that moment — so it
was left out rather than over-animate.

**New/changed:** `Session::celebrate/takeCelebration`; `PetController` +
`CreatureController` set/consume the flag; CSS touches across `layout.css`,
`components.css`, `creature.css`; tests for the celebration flag (set on a real
pet, not on cooldown; shown once then cleared).

---

## Art import — the artist's real logo and 404 artwork

Until now the masthead was a typographic stand-in and the favicon was a little
inline moon. Both are now the artist's real pixel art.

**Where artwork lives.** Finished site artwork goes in `public/assets/art/`.
(Creature sprites keep their own folder, `public/assets/creatures/<species>/`,
because they are looked up by species slug and life stage.)

| File | Used by | Why |
|---|---|---|
| `art/favicon.png` | `templates/layout.php` | the browser-tab icon |
| `art/logo-small.png` | `templates/layout.php` | square badge — the phone masthead |
| `art/logo-large.png` | `templates/layout.php` | wide banner — the desktop masthead |
| `art/not-found.gif` | `templates/pages/not-found.php` | the animated 404 |

**How the logo picks a version.** The masthead uses the standard HTML `<picture>`
element. The plain `<img>` inside it is the phone version, and a `<source>` with
`media="(min-width: 640px)"` offers the wide banner above that width. The browser
reads the rule and downloads **only** the file it will actually show — so this is
responsive art with no JavaScript at all. The square badge only reads "Felkyo", so
a small "Creatures" line sits under it on phones; CSS hides that line from 640px
up, where the banner already contains the word.

**Two things worth knowing before you touch the images:**

1. **Do not add `image-rendering: pixelated` to the logo or the 404 art.** That
   setting is for pixel art being scaled *up* (the creature portraits), where it
   keeps the pixels crisp. These images only ever display at their own size or
   smaller, and forcing it on a shrinking image makes it look ragged. The comments
   in the CSS say so too, so nobody "fixes" it later.
2. **`tests/Unit/ArtAssetsTest.php` guards the artwork.** It checks both that each
   file exists in `/public` and that the template really points at it — because a
   broken image is a silent failure that HTML alone cannot reveal. **If you add new
   artwork, add it to that test.**

**Still waiting on clean files.** The shop artwork (`shop-background.gif`,
`shopkeeper.png`, `item-cosmetic.png`) was delivered with a large "Felkyo"
watermark stamped across it, so it is **not** in the site. It is parked in
`public/assets/_incoming/shop/` until unwatermarked versions arrive. A default
avatar was also delivered, but there is no user-profile page in the plan for it to
appear on, so it is parked too.
