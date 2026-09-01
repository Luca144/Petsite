# Felkyo Creatures — briefing for the first external security review

*Written at the M2 checkpoint (2026-09-01), for whoever performs the
review. The build plan schedules this review HERE, before five further
milestones are built on this code: architectural problems are still
cheap now.*

## What you are looking at

A small virtual-pet site (PHP 8.2, no framework, MySQL, Plates
templates) run by two hobbyists with day jobs. It is currently a closed
demo, but it is built to open registration to the public — **and a
meaningful share of the eventual players will be children.** That is
the risk lens for everything: the worst outcome is not data loss, it is
a stranger reaching a child, or a child's account and creatures taken.

Setup: `docs/setup-guide.md` (XAMPP-style local run, Phinx migrations,
`php vendor/bin/phpunit`, `php bin/smoke-test.php` drives the real site
over HTTP). Architecture notes: `docs/developer-guide.md` (one chapter
per increment), `docs/schema.md`, `docs/free-text-safety.md`.

## Scope (from the build plan, in priority order)

1. **The role and permission model.** Four additive roles (owner,
   moderator, artist, coder) in `user_roles`; `src/Admin/AdminGate.php`
   is the single chokepoint — every `/admin` route is registered through
   `$adminGate->protect(Role::X, handler)` in `public/index.php`. Roles
   are read fresh per request; the panel door re-confirms the password
   and TOTP (`src/Admin/Totp.php`, hand-rolled RFC 6238 — please review
   it; recovery codes hashed and single-use). Question to answer: can
   any request path reach admin behaviour without passing the gate, and
   can any role reach another role's tools? (Our own matrix test:
   `tests/Integration/AdminGateTest.php`, ~90 routes.)
2. **Admin route authorisation per request** — including the M2.7
   moderation actions (`src/Moderation/ModerationService.php`: nobody
   moderates themselves or staff; targets derived from reports
   server-side) and the M2.8 settings (`src/Admin/SettingsCatalog.php`
   is a fixed allow-list; there must be no way to write a config key
   outside it, including by planting rows in `site_settings`).
3. **The upload path** (`src/Art/`): content-sniffed image validation
   (hand-written GIF/PNG/WebP parsers that strip metadata), generated
   content-hash filenames, served via an early front-controller exit
   from a non-executing path. The classic questions: polyglot files,
   decompression bombs, path traversal on serve, anything the parsers
   mis-swallow.
4. **The audit log's completeness** (`src/Admin/AuditLogRepository.php`,
   append-only by test): is every privileged mutation actually recorded
   — roles, art, all four content editors, all moderation actions, all
   settings changes and maintenance buttons?

Worth a look beyond the four: the player-facing free-text pipeline
(`src/Safety/` — TextGuard, ImpersonationGuard, the report system and
its hide-on-report trade, documented in `docs/free-text-safety.md`),
session handling (`src/Auth/Session.php`), CSRF (`src/Http/Csrf.php`),
and the rate-limit coverage of state-changing routes.

## Known, deliberate trade-offs (so you don't re-discover them)

- Reporting a bio/about HIDES it until review; spite-hiding is limited
  by one-report-per-person and surfaced reporter records (queue rows).
- Names never hide on report (hiding a name would let anyone erase a
  player); they are flagged and force-renameable instead.
- `security.moderation.appeal_email` is a placeholder until the team
  sets the real address — flagged in the handover.
- No order ledger exists (stats say so honestly); currency moves are
  transactional but not journaled.
- The art volume has no platform backups; the M2.8 ZIP button is the
  backup story.

## What we would like back

Findings ranked by "what should not survive the week" versus "worth a
quiet hour", with a line on exploitability from a real browser — this
team fixes by priority, not by count.
