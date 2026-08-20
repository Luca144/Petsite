# M2.1 — Admin foundation and roles: the plan

**Status: SIGNED OFF 2026-08-20 (plan as written; TOTP in scope; numbers as
proposed) and built the same day. See `decisions.md` for the sign-off record
and the two small decisions made during the build.**

This is the mandatory written plan required by `CLAUDE.md` §7 — M2.1 touches
authentication and permissions, so the three security questions are answered
here *before* anything is built. The increment is specified in
`felkyo-build-plan-2.md` (M2.1) and `felkyo-milestones.md`.

---

## 1. What M2.1 builds

The foundation everything else in M2 stands on: roles, an authorisation
chokepoint every admin request passes through, an audit log, hardened admin
sessions, and the first two panel screens (the panel home and role
management). It does **not** build any content screens — those are M2.3+ and
each will slot into this foundation.

**In scope:**

1. **Four roles** — owner, moderator, artist, coder — stored per user,
   additive (one person can hold several).
2. **An authorisation gate** that runs on *every* admin request, not only at
   entry, and reads roles fresh from the database so a revoked role stops
   working on the very next request.
3. **An audit log**: who, what, when, before, after — written by every admin
   action, starting with the actions this increment itself introduces.
4. **Admin session hardening**: entering the panel requires re-confirming the
   password ("the panel door"), and that confirmation expires after a short
   idle window. Session ID regenerated on each confirmation.
5. **Panel screens**: `/admin` home (tiles for the sections your roles allow),
   role management (owner only), a read-only audit log view (owner only), and
   the password-confirm screen.
6. **Bootstrap path for the first owner**: a CLI script run on the server —
   never a web endpoint.
7. **Rate limits** on the panel door and on role changes, as named config
   values.
8. Tests (the refusal matrix, below), a smoke-test chapter, and documentation.

**Out of scope, deliberately:** image upload (M2.2 — MAJOR DECISION, needs
hosting verification and Product Owner approval first), all content screens
(M2.3–M2.5), the report queue and moderation actions (M2.7 — the moderator
role exists from day one but its screens come later; until then a moderator
sees an honest "your tools arrive with M2.7" note, not a broken tile),
site settings (M2.8), stats (M2.9).

---

## 2. Data model

One migration, two tables. **This migration must be named in the first
paragraph of the handover — migrations never deploy themselves.**

### `user_roles`

A join table rather than a column on `users`, because roles are additive —
the artist can also be an owner. One row per (user, role).

| column | type | why |
|---|---|---|
| `id` | pk | |
| `user_id` | int unsigned, FK → users | who holds the role |
| `role` | varchar(20) | one of the four names, allow-listed in PHP |
| `granted_by_user_id` | int unsigned NULL, FK → users | who granted it; NULL means the CLI bootstrap script |
| `granted_at` | datetime | |

Unique index on `(user_id, role)` so a grant is naturally idempotent — the
same grant arriving twice at once cannot create two rows. Index on `role`
for "list all moderators".

A `src/Admin/Role.php` allow-list class holds the four names (same pattern as
`src/Safety/ReportSubject.php`), and `SchemaTest` asserts the PHP list and
the database contents agree.

### `admin_audit_log`

| column | type | why |
|---|---|---|
| `id` | pk | |
| `actor_user_id` | int unsigned NULL, FK → users | NULL = the CLI script |
| `action` | varchar(50) | allow-listed in PHP (`role.granted`, `role.revoked`, `panel.entered`, …); grows with each M2 increment |
| `subject_type` / `subject_id` | varchar(30) NULL / int NULL | what was acted on (polymorphic, like `reports`) |
| `detail_before` / `detail_after` | text NULL | JSON snapshots of the changed values |
| `ip` | varchar(45) | IPv6 fits |
| `created_at` | datetime | |

Indexes: `(actor_user_id, created_at)` and `(created_at)` — the two ways the
log is read (per-person history; recent activity).

**Append-only by construction:** the repository has insert and read methods
only. No update, no delete, no web endpoint that removes rows. Pruning old
entries, if ever needed, is a deliberate future decision — not a button.

---

## 3. The authorisation design

### The chokepoint

The router has no middleware concept, so the gate is applied where routes are
*registered*: every admin route in `public/index.php` is declared through one
helper that wraps the controller in `AdminGate`. It is structurally
impossible to add an admin route that skips the gate — the gate is the only
way to register one. On **every request** the gate, in order:

1. Resolves the session user. Not logged in → redirect to `/login`.
2. Loads the user's roles **fresh from the database** — never cached in the
   session, so revocation takes effect on the next request, not the next
   login.
3. No relevant role at all → the same **404** a player gets for any unknown
   URL. The panel does not confirm its existence to non-staff. (Golden rule 3
   — "never a dead end" — is for players on player paths; players are never
   shown a door to this place, so there is no dead end to soften.)
4. Checks the route's required role. Owner passes every check ("owner —
   everything"). A role-holder on a route outside their role → a plain-worded
   403 page naming which role the screen needs.
5. Checks the panel-door confirmation is fresh (see §4). Stale → the
   password-confirm screen, then back to where they were going.

### Role semantics

- Owner: everything, including role assignment and the audit view.
- Moderator / artist / coder: exactly their own sections, per the build plan.
- Additive: checks ask "does this user hold role X (or owner)".

### Role assignment (owner only)

A form on the role screen: pick a user (by exact username — typed, not a
browsable list), tick roles on or off. Server-side, every change:

- verifies the actor holds **owner** (via the gate *and* again in the
  service — belt and braces on the single most dangerous write on the site);
- validates the role against the PHP allow-list;
- verifies the target user exists;
- refuses removing the **last owner** — the site must never end up with
  nobody able to assign roles (checked inside the same transaction as the
  delete, so two concurrent revocations cannot race past it);
- refuses an owner revoking their **own** owner role (the last-owner rule's
  simple companion: you hand the keys over first, then someone else removes
  yours);
- writes an audit entry with before/after role lists;
- is rate-limited (see §6).

### Bootstrap: the first owner

`bin/grant-role.php <username> <role>` — run on the server by whoever has
shell access, exactly like migrations. It validates against the same
allow-list and writes the same audit entry (actor NULL, recorded as CLI).
**No web path can create the first owner**, and no registration or profile
endpoint touches `user_roles` at all — the only two writers are this script
and the owner-only service.

---

## 4. Admin sessions are more sensitive — concretely

Player sessions stay as they are. Admin surface adds, all as named values in
`config/config.php` `security` (numbers are proposals for sign-off):

| value | proposal | why |
|---|---|---|
| `admin_confirm_max_age` | 30 minutes | entering any admin route more than 30 min after the last password confirmation asks for the password again. A panel tab left open on a lost or shared device goes cold on its own. |
| `admin_confirm_rate_limit` | 5 attempts / 15 min, keyed on **user id and IP together** | the panel door must not be a password-guessing oracle |
| `role_change_rate_limit` | 10 / hour per actor | role changes are rare; a burst is an incident |

Plus, not numbers:

- **Session ID regenerated** on every successful panel-door confirmation
  (alongside the existing regeneration at login/logout).
- **CSRF token rotated at login** for everyone — today it lives for the whole
  session (`src/Http/Csrf.php`); rotating at authentication is one line of
  hardening the admin surface should not launch without.
- The confirmation timestamp lives server-side in the session, never in a
  cookie value the browser could edit.

---

## 5. The three security questions (CLAUDE.md §7)

### 1. Who is allowed to do this, and how is that enforced?

Only users holding a role in `user_roles` may reach any `/admin` route; only
an **owner** may view or change roles or read the audit log. Enforced by
`src/Admin/AdminGate.php`, wrapped around every admin controller at route
registration in `public/index.php` — there is no way to register an admin
route without it — re-checked on **every request** with roles read fresh
from the database. The role-change service re-verifies owner independently
of the gate. The UI hiding buttons is presentation only; every check assumes
the request arrived directly.

### 2. What is the worst thing a malicious user could do with this endpoint?

Whoever reaches the panel owns the site, so the walk is threat by threat:

- **Escalate via a normal endpoint** — impossible by construction: nothing
  outside the owner-only service and the CLI script writes `user_roles`, and
  no existing endpoint accepts a role parameter (tested: posting role fields
  at registration/profile does nothing).
- **Change an ID in the role form** — the actor check is on the *session*
  user, never on anything posted; the target is validated to exist; the role
  is allow-listed; forging `granted_by` is impossible (server-set).
- **Guess an admin password at the panel door** — rate-limited per user+IP,
  audit-logged, and the session behind it already required a full login.
- **Steal an admin session** — cookies are already HttpOnly/Secure/SameSite;
  added: 30-min confirmation expiry, ID regeneration at the door. Residual
  risk is why 2FA is raised below.
- **CSRF an owner into granting a role** — every admin form carries the CSRF
  token; SameSite=Lax blocks cross-site POSTs; token rotated at login.
- **Race two revocations to strand the site ownerless** — last-owner check
  runs inside the delete's transaction.
- **Replay a grant** — the unique `(user_id, role)` index makes a duplicate
  grant a no-op, not a second row.
- **Cover their tracks** — the audit log has no delete path, and every role
  change and panel entry is in it.
- **Learn who the staff are** (targeting) — role-holding is not exposed on
  any player-facing page, the panel 404s for non-staff, and the role screen
  takes a typed exact username rather than offering a user list.

### 3. What does the test suite prove about the above?

Every boundary gets a test that it **refuses** the bad case:

- **The refusal matrix**: each of {logged out, plain player, moderator,
  artist, coder} is refused on every admin route outside their scope, and
  each role is *admitted* to its own routes — one data-driven integration
  test over the full route list, so a future admin route added to the table
  is automatically covered.
- Role assignment refused for every non-owner role, even with a valid CSRF
  token and well-formed input.
- Unknown role name refused; nonexistent target user refused.
- Removing the last owner refused; an owner revoking their own owner role
  refused.
- Revocation is immediate: revoke, then request an admin route in the same
  test → refused.
- Posting `role`/`is_admin`-shaped fields to registration and profile
  endpoints changes nothing in `user_roles`.
- Panel door: wrong password refused, attempts rate-limited, stale
  confirmation re-prompted.
- Missing/invalid CSRF token refused on every admin POST.
- Every admin action leaves exactly one audit row with correct actor,
  action, before and after; the repository exposes no delete.
- CLI script grants correctly and refuses unknown roles.
- Smoke test chapter: seed an owner, walk the real panel over HTTP —
  confirm password, grant and revoke a role on a second account, verify the
  audit view shows it — and confirm a fresh player account gets 404 on
  `/admin`.

---

## 6. The panel as a product

The panel is a user-facing product too (build plan, principle (f)): the same
golden rules, mobile-first at 360px, no dropdowns ever, accessibility
included. Concretely for M2.1:

- `/admin` home is **tiles**, one per section the viewer's roles allow, in
  the site's own visual language — parchment on the starfield, same theme
  tokens, nothing "corporate dashboard".
- Role management uses toggle buttons per role (no select elements), plain
  words ("Skerro can now edit content" — not "role artist granted"),
  visible confirmation after every action (golden rule 4).
- A role-holder refused from a screen is told *which role it needs* — plain
  words, never a bare 403 code.
- The panel entry link appears in the personal sidebar only for role-holders
  (server-side check; the link's absence is convenience, the gate is the
  security).
- Keyboard reachable, visible focus, 44px targets, AA contrast — as
  everywhere; verified by eye with `php bin/gui-shots.php` at 360px and
  1280px before handover (golden rule 12).

---

## 7. New files (respecting the size limits)

```
src/Admin/
  Role.php                  allow-list of the four role names
  RoleRepository.php        reads/writes user_roles (all SQL commented)
  RoleAssignmentService.php owner check, allow-list, last-owner guard, audit
  AdminGate.php             the per-request authorisation chokepoint
  AuditLogRepository.php    append + read, no delete
  AuditLog.php              row value object
src/Http/Controllers/
  AdminHomeController.php
  AdminRoleController.php
  AdminAuditController.php
  AdminConfirmController.php   the panel door
templates/pages/admin-*.php, templates/partials/admin-*.php
public/css/admin.css
bin/grant-role.php
migrations/<date>_create_admin_foundation.php
```

Docs updated: `docs/schema.md` (both tables), the developer guide (how to
bootstrap an owner, how to add an admin route through the gate — the
copyable recipe), `decisions.md` entry once signed off.

---

## 8. Open questions — raised, not decided

### (a) Second factor for admin accounts — the plan recommends yes; so do I

**Recommendation: build TOTP into M2.1, required for every role-holder.**
The panel is the highest-value target on the site and it opens this
milestone; the strongest version of "admin sessions are more sensitive" is a
second factor, and retrofitting it later means a window where it isn't there.

Proposed shape, honestly costed:

- **TOTP (authenticator app), implemented in vanilla PHP.** RFC 6238 is
  `hash_hmac` plus base32 — roughly 80 lines plus tests, no new dependency
  (matching the house rule of small code over packages).
- **Enrolment at first panel entry**: the secret shown as text to type into
  any authenticator app, with the `otpauth://` URI alongside. **No QR code
  initially** — QR generation would mean a dependency; typing a secret once
  is mildly clunky, and if it proves annoying for real staff a QR library is
  a separate, small, ask-first decision.
- **Recovery codes**: a one-time set shown at enrolment, stored hashed like
  passwords — losing a phone must not mean losing the site.
- The code is asked for at the panel door alongside the password confirm;
  attempts share the door's rate limit; enrolment and use are audit-logged.

The alternative is deferring 2FA to a follow-up before M2.2 (upload) goes
live — smaller M2.1, but a live panel window without it. Your call.

### (b) The proposed numbers

30-minute panel-door expiry; 5 door attempts per 15 minutes; 10 role changes
per hour. Named config values either way — say the word and they change
without a deploy from M2.8 onward.

### (c) On the horizon, not part of this sign-off

**M2.2 (image upload) is a MAJOR DECISION**: before any code, hosting must
be verified for where uploaded files can actually persist, options presented
(persistent volume / object storage / repo), and your approval given. Noted
here so it isn't sprung on you mid-milestone.

**Parked for Skerro, untouched by M2.1:** adoption-retirement sign-off and
mini-game reward scope. Nothing here builds against either.
