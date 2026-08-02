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

- **Controllers / route handlers** decide *what to do* for a request.
- **Services** hold *game rules* (coming in later increments).
- **Repositories** own *database access* (coming in later increments).

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

**Note on the logo:** the masthead wordmark is a typographic placeholder set in
Fraunces. The finished logo art replaces it during the art-import step.
