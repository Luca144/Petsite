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
| `components.css` | The reusable **components** — buttons (`.btn`, `.btn--primary`, `.btn--secondary`), cards (`.card`, `.card--dark`), tiles (`.tile-grid`, `.tile`), form fields (`.field*`), and badges. The bare `.btn` base already carries the quiet secondary look, so a button whose variant class is forgotten still renders themed instead of browser-default white — but still name the variant you mean. |

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
as ordinary files in Git — **not** in Git LFS. `.gitattributes` explains why at
length; the short version is that the art is about 1 MB in total, and Railway does
not fetch LFS files when it builds, so putting art back into LFS would silently
break every image on the live site.

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

---

## Increment C.4 — The guestbook

**What it delivers:** visitors can sign a creature's guestbook. Each person leaves
**one entry per creature**, chosen from a **fixed list of messages**, and may change
their entry **once a day**.

### The design decision that makes this safe

A guestbook is normally the most dangerous thing on a small site: free text from
strangers means spam, abuse, and moderation forever. Ours removes the danger at the
root — **there is no free typing anywhere in it.** A visitor picks one of the
messages defined in config, and we store only its short key.

Because no visitor-written text is ever stored, there is nothing to spam with,
nothing to filter, and nothing to moderate. The safety comes from the shape of the
feature, not from a filter fighting a losing battle. Keep it that way: **adding a
free-text field to the guestbook would undo this entirely**, and would need real
spam protection built first.

### Where the pieces live

| File | Job |
| --- | --- |
| `config/config.php` → `gameplay.guestbook` | the messages, the once-a-day setting, how many entries a page shows |
| `src/Guestbook/GuestbookMessages.php` | the catalogue: "is this a real message?", "what does this key say?" |
| `src/Guestbook/GuestbookRepository.php` | all the SQL |
| `src/Guestbook/GuestbookService.php` | the three rules (see below) |
| `src/Guestbook/GuestbookPanel.php` | gathers what the creature page needs to display |
| `src/Http/Controllers/GuestbookController.php` | who is allowed to sign at all |
| `templates/partials/guestbook.php` | the entries and the chooser |
| `public/css/guestbook.css` | its styles |

### The three rules, and where each is enforced

1. **Only an offered message may be chosen** — `GuestbookService`, via
   `GuestbookMessages::has()`. This is what stops someone editing the form in their
   browser and submitting a key we never offered.
2. **One entry per person per creature** — the **unique database index** is the real
   guarantee; the service checks first only so it can give a friendly message and
   change the existing entry rather than fail.
3. **One change per day** — `GuestbookService`, measured from `updated_at`. Choosing
   the message that is *already* stored is a deliberate no-op that does **not** use
   up the day's change; spending someone's one change on nothing would be an
   unpleasant surprise.

Permission questions (logged in? allowed to see this creature?) live in the
controller instead, so game rules and web plumbing stay separable.

### Two notes for whoever works on this next

- **The chooser must stay radio buttons.** They are real `<input type="radio">`
  elements styled as cards, so keyboard use and screen-reader announcements work for
  free. A dropdown is forbidden (`CLAUDE.md` section 8) and a test in
  `CreatureControllerTest` fails if one appears.
- **Retiring a message is safe.** Delete the line from config; entries that used it
  fall back to neutral wording instead of breaking. But **never reuse a key for a
  different meaning** — old entries would silently start saying something their
  author never chose.

### How to add or reword a message

Edit `gameplay.guestbook.messages` in `config/config.php`. That is the whole job —
no code, no migration. Write plain text, not HTML entities: the templates escape
everything on output, so `&rsquo;` would appear literally on the page.

**Deliberately allowed:** the creature's own owner may sign their creature's
guestbook, matching how petting works (you may pet your own creature). This was
confirmed as intended, not an oversight.

### Removing an entry — note whose permission it is

A creature's **owner** can remove any entry from their guestbook. Read that
carefully, because it is the opposite of the bio rule and it catches people out:

> The person allowed to delete is the **creature's owner** — *not* the person who
> wrote the entry.

The guestbook belongs to the creature, not to the visitors who signed it. An author
cannot delete their own message; they can change it once a day instead. Both halves
are pinned down by tests in `GuestbookDeletionTest`, so nobody "corrects" the rule
into the more familiar-looking one later.

**After a removal, that person may sign again.** This is intended behaviour, and it
comes for free: the row is deleted outright rather than marked as deleted, which
frees the unique `(creature_id, author_user_id)` pair. No "deleted" column, no
special case — but there *is* a test for it, because it is a promise rather than an
accident.

**One detail in the SQL worth copying.** `deleteFromCreature()` names the creature
in the `DELETE` as well as the entry id, even though the controller has already
checked ownership:

```sql
DELETE FROM guestbook_entries WHERE id = :id AND creature_id = :creature_id
```

That second condition means even a wrong entry id — a stale page, a mistyped
address, somebody experimenting — can only ever touch an entry in *that* creature's
guestbook. Two locks on one door is cheap; the cost of the alternative is a visitor
deleting a stranger's message.

Deletions are rate-limited under their own action key (`guestbook_delete`) while
sharing the guestbook's limit *settings*, so a burst of deleting can never use up
somebody's allowance for signing.

---

## Increment D.1 — Production preparation

**What it delivers:** the three things the site needs before it can go live as a
**closed demo** — a switch that closes registration, a script that populates demo
content, and a banner saying this is a demo.

Full instructions live in [deployment-guide.md](deployment-guide.md). This section
covers how the pieces work in the code.

### A bug worth understanding: where settings actually come from

Building this uncovered a real problem that would only have appeared on the live
server, silently. It is worth reading, because the lesson applies far beyond this
project.

Config used to read settings from `$_ENV` alone:

```php
'environment' => $_ENV['APP_ENV'] ?? 'development',
```

That works perfectly on your machine, because the `.env` file's values are put into
`$_ENV` by phpdotenv. **But a hosting platform has no `.env` file** — it supplies
settings as *real* environment variables, and PHP only copies those into `$_ENV`
when a `php.ini` setting called `variables_order` includes an `"E"`, which it very
often does not.

So on the live server every setting would have quietly fallen back to its
*development* default. Nothing would have looked broken. And one of those defaults
is `registration_open` — **the closed demo would have been accepting real sign-ups.**

Two changes fixed it, both in the "configuration comes from the environment" spirit
of `CLAUDE.md` section 6:

1. **`config/config.php` reads through a small `$readEnv` helper** that checks
   `getenv()` first (which always sees real environment variables), then falls back
   to `$_ENV`/`$_SERVER` (where `.env` values land). Real environment variables win,
   because what a server is configured to say must beat a file that happens to be
   lying around.
2. **`config/bootstrap.php` no longer dies when there is no `.env`.** It loads the
   file when present and carries on when absent — but still gives the clear
   first-run message when there is *neither* a `.env` *nor* anything in the
   environment, which is the actual mistake it was written to catch.

`tests/Unit/ConfigEnvironmentTest.php` guards all of this: it clears `$_ENV`, sets a
real environment variable, and checks the config sees it — plus that production
defaults to registration closed, and that a typo like `REGISTRATION_OPEN=yes` fails
*safe* rather than opening the site.

### Two settings, with defaults that depend on the environment

`config/config.php` now reads `APP_ENV` into a variable first, because two settings
take their default from it:

| Setting | Env variable | Default in development | Default in production |
| --- | --- | --- | --- |
| `app.registration_open` | `REGISTRATION_OPEN` | open | **closed** |
| `app.show_demo_notice` | `SHOW_DEMO_NOTICE` | hidden | **shown** |

Defaulting by environment means the live site is closed **even if somebody forgets
to set anything** — the safe state is the one you get for free. A small helper
closure, `$readFlag`, turns the environment's `"true"`/`"false"` text into real
booleans in one place instead of repeating the same comparison.

### The registration switch, and why it is checked twice

When registration is closed:

- `templates/layout.php` hides the "sign up" link,
- `RegisterController::show()` returns a friendly page with HTTP 403,
- `RegisterController::submit()` **also** returns it, before doing anything else.

That last one is the important one. **Hiding a form does not stop anyone posting to
its address** — a bookmark, a script, or a curious person with the browser's
developer tools will all reach `POST /register` directly. So the refusal lives on
the action itself; the hidden link is only a courtesy. This is a general lesson
worth carrying to every feature you build: enforce rules where the work happens,
not where the button is drawn.

### The seed script

`seeds/DemoContent.php` is a Phinx seeder (Phinx was already a dependency for
migrations, so this needed nothing new). It creates three demo players, four
creatures, and some pettings and guestbook entries so the demo does not look
abandoned.

Three details worth copying if you write another seeder:

- **It looks species up by slug**, not by guessing id numbers, so it works whatever
  ids the database happened to assign.
- **It is safe to run twice** — it checks whether the demo accounts already exist
  and returns early. Somebody will run it twice.
- **It refuses to run without `DEMO_ACCOUNT_PASSWORD`** rather than inventing a
  weak default. These accounts sit on a public URL; a password written in the code
  would be a password everyone with the repository knows.

### Tests

- `RegistrationClosedTest` — the switch, from both directions, and specifically
  that a direct POST creates no account while it is closed.
- `DemoSeedTest` — actually runs the seeder against the test database, checks the
  world it builds, checks running it twice changes nothing, and checks no seeded
  creature is left without an owner or species. A seed script is run once, on the
  live server, at the worst possible moment to discover a typo — so it is tested
  here instead.

---

## Increment M1.1 — The owned-thing model

The first increment of build plan 2, and the schema decision the rest of that plan
rests on. **The full reasoning lives in `docs/owned-things.md`** — this section is
the short version of what changed in the code.

### The decision

Players will own four kinds of thing: items, creatures, plants and fish. They come
in **two shapes**, not four. Items are *counted* — one honey treat is as good as
another, so we store "this player has three". Creatures, plants and fish are
*individual* — each is named and has its own history, so each gets its own row.

Each individual kind gets **its own table**. Creatures already had one; fish and
plants get theirs at M10 and M12, following the recipe in `docs/owned-things.md`.
A single everything-table and a shared-core-plus-details design were both
considered and turned down; the doc says why, at length, because the next person
to have the idea deserves the reasoning rather than a "no".

### What actually changed here

Deliberately little, because the decision is most of the increment.

- **`items` gained `sell_value`** — what a player gets back for selling one, kept
  strictly apart from `price`, which is what a shop charges. If an item could ever
  sell for more than it costs, buying and selling in a loop would mint unlimited
  currency. Two named columns make that rule testable; one column doing both jobs
  would have hidden it. `sell_value = 0` means "not sellable", which is different
  from "sells for nothing" — it lets the site explain itself instead of offering a
  button that takes something and gives back zero.
- **`OwnedItemStack`** replaced the loose `['item' => ..., 'quantity' => ...]`
  array the inventory used to hand around. A named type says what a thing *is*; an
  array only says what shape it is. It is also where "can this be sold?" now lives.
- **`CreatureRepository::updateBio()` now names the owner in its `WHERE` clause**
  and takes the id of the person doing the editing. The controller's permission
  check was correct and stays — this is a second layer underneath it.

### The rule worth remembering

**Every method that reads or changes something a player owns takes the acting
player's id and puts it in the `WHERE` clause.** Never fetch by id and check the
owner in PHP afterwards.

Both work when written carefully; they differ in what happens when they are not. A
check in PHP is a line somebody can delete during a tidy-up, and a missing `if` is
invisible because there is nothing there to see. An owner named in the query fails
loudly-by-doing-nothing instead. Every form on this site carries an id, and editing
one by hand takes seconds — so the check belongs where forgetting is impossible.

### Tests

- `OwnedThingOwnershipTest` — new, and written entirely from the stranger's side:
  Rowan tries to read and change Mira's things, and is refused each time. It calls
  the repository **directly**, going around the controller on purpose, because that
  is a rehearsal of the day somebody refactors the layers above and drops a check.
- `ItemSellValueTest` — walks every item every shop actually offers and fails by
  name if one sells for more than it costs. This is a test of *content*, not code:
  the realistic way the rule gets broken is a generous number typed into the panel
  one evening (M2.4), so it guards real data rather than an invented example.
- `OwnedItemStackTest` — what "sellable" means, including the empty-pile case
  (someone left the inventory open in a tab and sold their last treat elsewhere).
- `SchemaTest` — also gained `guestbook_entries`, which was missing from a list
  that claims to be exhaustive.

### One thing that was planned and then dropped

A shared `OwnedThing` interface, so one screen could render any owned thing, was in
the plan and did not survive contact with the code: a `Creature` cannot supply its
own picture path (that is built from the species slug and life stage, which live
elsewhere), so the shared type could not carry the thing a shared screen most
needs — and nothing yet renders two kinds through one path. It is recorded in
`docs/owned-things.md` §6 rather than quietly forgotten. If M1.2's item card and a
later creature card genuinely converge, extract it then, from two real cases.

---

## Increment M1.2 — Item categories, the item card, selling and discarding

Where the owned-thing model of M1.1 becomes something a player can see and use.

### Categories replaced a free-text column

An item's kind used to be a `type` column holding whatever string somebody typed
— "sticker", "treat". That was fine for four items. It stops being fine when the
kind has to carry a colour, an icon and a label, because one stray "Stickers" and
an item quietly falls out of its own group with nothing to warn you.

`item_categories` is now a real table with a foreign key, and `type` is gone —
two answers to "what kind of thing is this?" would have drifted apart the first
time somebody updated one and forgot the other.

The eight categories — ingredient, dish, potion, material, seed, tool, sticker,
badge — come from the artist's design document, not from invention.

### The three signals

Every category says what it is three times: a **tint**, an **icon**, and a
**word**. Not belt-and-braces fussiness — it is the only arrangement that works
for everybody. The tint is fastest to read and fails colour-blind players; the
icon is recognisable at a glance but has to be learnt; the word never fails
anyone. Together they cost one line of markup.

`ItemCategory` makes the name a required part of a category, so there is no way
to render a card that is colour-only even by accident.

### Colours live in the theme, never in a component

A category stores the **name of a theme token** (`--category-dish`), not a colour.
The card sets `--card-tint` from it and the stylesheet uses that. No colour value
is written in any template (CLAUDE.md section 8), so the themes of M4 will restyle
every card at once.

**`CategoryContrastTest` opens the real `theme.css`, reads every colour out of it,
and fails if text could not be read on any category tint.** It reads the actual
file rather than a copied list, because a copied list drifts and would eventually
pass while the site was broken. This is the small ancestor of M4.2's live contrast
checking, and it uses the same `ColourContrast` class, so there will be one
definition of "readable" rather than two that disagree.

There is also a test asserting that **gold on parchment still fails**. Nothing
uses that pair; the test protects the *rule* by showing the number to anyone who
suspects the ban is fussy.

### Artwork appears by itself

Item art is found by convention at `public/assets/items/{slug}.png`. The card and
the item page check whether the file exists: if it does they show it, and if it
does not they show the category's icon as a stand-in. Drop a drawing in and it
appears — nothing to edit, nothing to remember, and nothing looks broken while the
artist is still drawing.

### Selling and discarding

The security thinking is written out in full at the top of `ItemDisposalService`.
The two things worth carrying in your head:

**The item goes first, the money second.** It reads more naturally to pay the
player and then take the item, and that order is wrong: if the removal then
failed, they would have been paid for something they still own.

**`InventoryRepository::removeOne()` is the whole defence against being paid
twice.** The "do they still have one?" check is in the `WHERE` clause, not in PHP
above it. Two requests arriving at the same instant — a double-tapped button on a
slow phone does this, as does anybody deliberately trying — queue up inside the
database. One matches a row, the other finds nothing, and only the first is paid.
**Its return value is not a courtesy; ignoring it re-opens the hole.**

Selling and discarding are separate routes rather than one route with a flag, so
the difference between "get paid" and "lose it for nothing" is never a value the
browser sends.

### Small things that were deliberate

- **Not owned and does not exist give the same answer.** Saying "that exists but
  is not yours" would confirm what other players own, one guessed number at a time.
- **Throwing something away asks first**, via a plain `<details>` panel — no
  JavaScript, works by keyboard, announced properly by screen readers.
- **An unsellable item explains itself** instead of hiding the button, because an
  absent button reads as a bug.
- **Selling your last one returns to the inventory**, not to an item page that
  would immediately say "you don't own one of those".

### The shop margin

`gameplay.economy.maximum_sell_fraction_of_price` (80%) is the Product Owner's
rule: a shop must always keep a margin, because the shopkeeper has to live off the
difference too. The number is calibrated from the artist's own pricing — her most
generous buy-back was 75%. `ItemSellValueTest` reads the ceiling from config so
the rule and the number can never drift apart.

The same rule is written to cover the NPC friendship discounts planned for M13,
which would otherwise open the loop from the other direction. See
`docs/owned-things.md` rule 3.

### Tests

`ItemDisposalServiceTest` (9), `ItemControllerTest` (8), `CategoryContrastTest`
(3), plus additions to `ItemSellValueTest`. Most are written from the attacker's
side: Rowan tries to sell Mira's things, and is refused.

### The recipe

`docs/adding-items.md` — how to add an item and how to add a category, for a
reader who has never programmed. The first of the "how to add one" documents.

---

## Increment M1.3 — Profiles, avatars, and making a page your own

Players stop being accounts and become somewhere you can visit.

### The avatar is a key, not a filename

`users.avatar_key` holds a short word like `default`, and `AvatarSet` is the only
thing that can turn one into a picture. If the column held a filename instead,
somebody could put `../../../.env` in it, or the address of their own server —
and then every visitor to their page would quietly fetch a file of their choosing,
handing them the IP address of everyone who looked. An allow-list closes both, and
costs one small class.

**Players never upload an avatar** (build plan M1.3). Uploaded pictures mean
moderating pictures, which is far harder than moderating text and is the easiest
route for something genuinely harmful onto this site. Recipe in
`docs/adding-avatars.md`.

### The profile page is the same page for everybody

The owner sees exactly what a stranger sees, plus an edit link and a quiet note
about hidden creatures. The build plan asks that players be shown clearly what
others can see, and the honest way to do that is for the page to *be* the page.

`Profile` is a separate value object from `User`, holding only what is public —
no email, no password hash, no balance. That is the protection: a template handed
a whole `User` would be one typo away from putting a private column on a public
page, and the typo would look perfectly reasonable in review. **The type is the
guard.** `ProfileRepository` never selects those columns either, so there are two
layers and neither relies on remembering.

### A private creature can never be exposed by featuring it

The public/private filter lives in `findForProfile()` — at the point of display,
not at the point of saving the choice. So a player who features a creature and
later makes it private does not have to remember to un-feature it; it simply stops
appearing. Their choice is still remembered in the edit form, so nothing is
silently thrown away.

Featuring somebody else's creature is closed twice: `ProfileService` keeps only
ids the player owns, and `ProfileRepository::replaceFeatured()` names the owner in
its `UPDATE` as well. Ids that are not theirs are dropped **silently** rather than
refused — a refusal would confirm that a guessed number belongs to somebody.

### There is no way to say whose profile to edit

Every method takes the acting player from the session. No route, no parameter and
no form field anywhere in `ProfileController` says whose page is being changed.
`testThereIsNoWayToSayWhoseProfileToEdit` submits `user_id`, `id` and `username`
all naming somebody else, and proves none of them does anything.

### No dropdowns, and the tiles are real form controls

Avatars and featured creatures are grids of tappable tiles — but underneath each
one is an ordinary radio button or checkbox, moved out of sight with `opacity` and
`position`, never with `display:none` (which would take it away from screen
readers and the keyboard too, defeating the point). Tab, arrow keys, space and
voice control all behave exactly as expected.

Chosen tiles are marked by a gold border **and** a thicker edge **and** the
checkbox state a screen reader reads out — never by colour alone.

### The about text, honestly

This is the first free text a player can put in front of another player. Today it
carries a length cap and the existing word filter, which is exactly the protection
the creature bio already has, and no more. **M1.4 is the increment that fixes this
properly** — links, lookalike characters, impersonation, reporting. The comment at
the top of `ProfileService` says so plainly rather than implying the field is safe.

### Tests

`ProfileServiceTest` (17) and `ProfileControllerTest` (10). The ones worth knowing
about: an avatar that is a file path is refused; a private creature never appears
even when featured; a profile never carries the email address; and Rowan cannot
edit Mira's page however he labels the request.

---

## Increment M1.4 — Names, bios, and the guards on them

The highest-priority safety increment in Part One. **The full reasoning is in
`docs/free-text-safety.md`** — read that one first; this is the code tour.

### One guard for all three fields

There are exactly three places on this site where a player chooses their own
words: an account name, a creature's name, and a bio or about text. All three go
through `TextGuard`. Sharing it is the point — three separate sets of rules would
mean three different sets of gaps, and the gap nobody remembered would be the one
that mattered.

`ProfileService`, `CreatureBioService` and `RegistrationService` all call it. The
old `ContentFilter` is no longer used by any of them.

### The link filter is the important one

`ContactDetailDetector` is not about spam. A stranger on this site can do very
little — no messaging, no private channel, no words of their own. Unless they can
persuade somebody to *leave*, at which point none of it applies. A link is the one
thing that leads somewhere none of this project's protections reach.

### Two false positives found while building it, both instructive

**"A gentle creature who loves naps."** was refused as advertising Snapchat —
`lovesnaps` contains `snap` once the spaces come out. Fix: the fully-collapsed
form is only matched against platform names of seven letters or more.

**"see example.com"** was reported as an email address. The normaliser turns `@`
into the letter it imitates (right, for catching `sc@m`), so *any* domain
containing an "a" looked like `something@something.com`. Fix: email detection
reads the original text, not the normalised form.

Both are why `TextSafetyTest` tests the **innocent** sentences first. A filter that
refuses ordinary writing gets switched off, and then protects nobody at all.

### Three forms of the same text

`TextNormaliser` produces three, and choosing the wrong one is how the bugs above
happened:

- `normalise()` — lowercase, lookalike digits mapped back, separators collapsed.
- `withoutLetterSpacing()` — closes up runs of *single* letters only, so
  `s c a m` becomes `scam` while `loves naps` stays two words. Note the negative
  lookahead: without it the run swallowed the next word's first letter and
  produced `scamartists`, in which `scam` is no longer a whole word.
- `withoutSpacing()` — closes everything. Blunt; long platform names only.

### Impersonation is stored, not computed

`users.username_skeleton` holds the dull comparison form, indexed. Checking a new
registration against every existing account would be fine with twelve accounts and
hopeless with twelve thousand — **and a check that gets slower as the site grows is
a check somebody eventually removes.**

The folding order in `skeletonOf()` matters: lookalike alphabets are folded
*before* accents, because the accent step drops characters it cannot convert, so a
Cyrillic "а" would simply vanish and `mirа` would become `mir` — no longer
resembling `mira` at all.

### Reporting

Fixed reasons, no free-text box — a "tell us more" field would be one player
writing words another player reads, which is the one thing this design does not
have. Reasons carry a priority and are offered most serious first.

One report per person per thing, enforced by a unique index rather than by looking
first: two taps arriving together would both pass a check-then-insert.

**Bios hide when reported; names do not.** Hiding a name would break every page it
appears on and would let anybody erase another player by reporting them. This is
recorded on `ReportSubject::hidesUntilReviewed()`, beside the kinds it applies to,
so adding a sixth kind forces somebody to answer the question.

**A known risk, deliberately taken:** because reporting hides a bio, somebody can
hide an innocent player's text out of spite. M2.7's queue **must** surface
reporters whose reports are always dismissed — that is a requirement of that
increment, not a nicety.

### Tests

`TextSafetyTest` (39, including data providers) and `ReportServiceTest` (13). The
innocent-text cases are as load-bearing as the refusals.

---

## Increment M1.5 — Finding a player

### Why search is safe here, when it usually is not

There is nothing harmful you can do with a player once you have found them on this
site. No messaging, no private channel, no words of your own — the worst somebody
can do with a found profile is send a card that everybody can see. **The danger in
"finding people" is what comes after finding them, and on this site that door is
already shut.**

So the threat search actually has to answer is not contact. It is **enumeration**:
somebody scripting their way to a list of everybody here, which is the first step
of anything else.

### Four separate answers to that one threat

- **Prefix matching only.** `mi%`, never `%mi%`. A "contains" search would turn a
  single common letter into a large slice of the playerbase; a prefix only answers
  a question somebody already half knows the answer to.
- **A minimum of two characters.** One letter is not a search, it is a way of
  listing everybody whose name begins with "a".
- **A small result cap** (20), so a wide prefix cannot be harvested in one request.
- **Rate limiting**, so working through the alphabet is slow and visible.

And **no endpoint anywhere returns players by recency or in bulk.** There is no
"newest members" list on this site, deliberately: new accounts are the least
familiar with how things work and the most likely to be young, so a list of recent
arrivals is precisely the tool somebody would want for finding them.

`testThereIsNoWayToAskForPlayersByRecency` walks `ProfileRepository`'s method names
and fails on anything called `recent`, `newest` or `all`. That is a blunt test on
purpose — this is a guarantee that would be easy to lose to a helpful addition.

### Wildcards are escaped

Without escaping, a search for `%` would match every name on the site: a search box
that lists the playerbase in one keystroke. `addcslashes` handles `%`, `_` and the
backslash itself.

### The opt-out is an unlisted number, not a closed door

`users.is_findable` (on by default) keeps a player out of search results. It does
**not** hide their profile — somebody who already knows the name can still visit.
Hiding the page as well would be a different feature with knock-on effects (their
creatures vanishing from browse, their guestbook entries dangling), and it is not
what was asked for. There is a test pinning this down so nobody later "fixes" it.

An unticked checkbox sends nothing at all, so the controller reads absence as "not
findable" — the safe direction for a privacy setting to fail in.

### A note on stylesheets

`profile.css` reached 385 lines, so the reporting and search styles moved to
`social.css`. CLAUDE.md caps a stylesheet at 400 for a reason: one you have to
scroll to understand is one nobody reads.

### Tests

`SearchControllerTest` (13). Most of them are about what search refuses to tell you.

---

## The smoke test — and why it exists

`php bin/smoke-test.php`

**Run it before telling anybody a feature is ready.** It is on the finishing
checklist in CLAUDE.md section 13, alongside the unit tests, and not as a
formality.

### What it does

Starts the site, registers an account through the real signup form, walks every
page, submits the real forms, and checks the pages **did their job** rather than
merely rendering. It also checks the markup mistakes that are cheap to make and
embarrassing to ship: images without alt text, repeated ids, skipped heading
levels, and anything the application logged as an error.

53 checks, about twenty seconds.

### Why it exists

The unit and integration tests call the code directly. That is fast and catches
most things — but it cannot catch a mistake in how the code is **wired together**,
because the test does the wiring, and it wires things the way the person writing
it believed they worked.

The player search read its query from posted form values. The search form is a GET
form, so the query arrives in the address. **Search never worked once.** Its
integration test passed every time, because the test built the request by hand and
put the query where the controller expected it — proving the controller worked
given input it could never receive.

A green suite said the feature was fine. Opening the page would have shown the
truth in four seconds.

### Two things it enforces about itself

- **It refuses to run against a production configuration.** It creates accounts
  and clears rate-limit records — fine on a laptop, unacceptable on the live site.
- **It clears the local address's rate-limit records first.** Registration is
  capped at three accounts an hour per address, which is right for the real site
  and stops the script running twice in a row.

### When you change it

Test the test. Break the thing on purpose, confirm the check goes red, then put it
back. The first version of the search check passed even with the bug reintroduced,
because the logged-in player's own name appears in the page header and the check
was looking anywhere on the page rather than inside the results.

---

## The second magic pass — navigation, petting, favourites

C.3 gave the site its first pass of craft, and it covered the pages that existed
then. Everything M1 added was built correct and left plain. This closed that gap
before the Product Owner saw it, and it came from three specific observations:
*"the navigation is just a random collection of buttons"*, *"petting a creature
doesn't really do much besides me clicking"*, and *"putting a creature as
highlight doesn't make it special"*. All three were right.

### The navigation was two different things pretending to be one

It was eleven identical pills in a wrapped row: every destination, plus the
player's name, their gems and the log-out button, all looking exactly alike. That
is why it read as a heap rather than a way of getting somewhere.

Two changes, neither needing a dropdown:

**Who you are is not navigation.** Name, purse and log out moved into their own
quieter strip below the links. They are status, not destinations.

**Where you can go is grouped** — *Yours*, *Wander*, *Everyone*. Three small
groups rather than nine in a line. The only thing separating them visually is
that the gap *between* groups is much larger than the gap *inside* one; no lines,
no labels, no headings. They are real `<ul>`s with `aria-label`s, so a screen
reader gets the same grouping the spacing gives everyone else.

Log out is now deliberately the quietest control on the page — transparent until
hovered. It is not a destination and nobody should hit it reaching for something
else.

The whole thing lives in `partials/site-nav.php`, because the layout was pushing
past its 200-line limit and the navigation reads better on its own anyway.

### Petting now looks like it did something

It already had one small heart, which was easy to miss entirely — you clicked, the
page reloaded, and nothing appeared to change. Three things now happen together:

- the creature **wriggles** — a squash and a lean, the way an animal shifts when
  it is enjoying being fussed over
- **three hearts** drift up, each starting slightly later and drifting differently,
  so it reads as a flurry rather than one thing repeated
- the **number that changed lights up**, because otherwise the change is invisible:
  the number was already there and is simply one higher

Deliberately gentle — this plays every time anybody pets anything, so bouncier
would wear thin within a day. All CSS, all skipped under `prefers-reduced-motion`,
and the flash message still says what happened in words, which is the test for
whether an effect was decoration in the first place.

### A favourite creature looks like one

Featuring a creature used to change only the order they appeared in, which nobody
notices — so the setting felt like it did nothing. A favourite now gets a warmer
card with a soft gold light behind it, a gold star, and the word "favourite".

Three signals again, for the same reason as the item categories: never the colour
alone, and never only the star, because an icon has to be learnt. On a very narrow
card the word is hidden visually but kept in the page, so screen readers still get
the signal that never fails anybody.

Gold text on deep plum is 7.8:1 — the one place gold text is allowed, since on
parchment it fails badly.

---

## The usability pass — moments, the purse, and the finder

*(Plan and reasoning: `docs/plan/2026-08-13-usability-pass.md`. Decisions in
short: `docs/plan/decisions.md`, entry 2026-08-13.)*

### Creature moments

One page load in five (tunable: `gameplay.creature_moments.chance_percent`),
one of your creatures pops up in a speech bubble at the top of the content —
portrait, line, and the whole bubble is a link to its page. The roll happens in
`CreatureMoments` (randomness injectable for tests), wired once in
`public/index.php`, rendered by `partials/creature-moment.php`.

**To change the site's voice**, edit `gameplay.creature_moments.lines` in
config — `{name}` becomes the creature's name. No reward may ever be attached
to a moment (see the decisions entry for why).

### The purse

Every logged-in page shows the balance as a status chip in the header
(`.site-purse`, styles in `site-nav.css`). It is deliberately not a link and
not pill-shaped — status should not dress as a control.

### The finder (how to reuse it)

Both economy pages narrow their lists the same way. To add a finder to a new
page of items later:

1. fetch the full list, then let `ItemFinder` do everything:
   `categoriesOf…()`, `validCategorySlug()`, `cleanSearchText()`, `filter…()`;
2. hand the template a `finder` array (see `partials/item-finder.php` for the
   keys) and insert the partial;
3. keep the URL shape `?category=<slug>&q=<text>` — it is bookmarkable, works
   without JavaScript, and is what a SQL-backed version would keep.

The search box shows itself from `gameplay.finder.search_shown_from` things up
(and always while a search is active); the pills appear whenever there is more
than one category. Both thresholds are display decisions only — the URL
parameters always work.
