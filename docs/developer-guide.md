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
