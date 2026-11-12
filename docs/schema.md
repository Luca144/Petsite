# Felkyo Creatures — Database Schema

This document describes the database in plain language: every table, what it is
for, and why it is shaped the way it is. It is kept in step with the migrations
in `migrations/`. The schema **only ever changes through a Phinx migration** —
never by editing tables by hand (CLAUDE.md section 5).

The whole schema was designed and approved up front (increment 0.2) so later
features slot in without painful rebuilds. Many tables are created before the
features that use them — that is deliberate.

## Conventions used everywhere

- Every table has an unsigned integer `id` as its primary key.
- Table names are plural and `snake_case`; column names are `snake_case`.
- `created_at` is set automatically when a row is inserted.
- Money and counts are **unsigned** integers — they can never go negative.
- **Required** columns are `NOT NULL`; only genuinely optional ones allow `NULL`.
- Foreign keys use `CASCADE` when the child belongs to the parent (delete a user
  → their creatures go too) and `RESTRICT` for shared content that must not be
  deleted while in use (you cannot delete a species a creature still uses).
- Columns that are looked up often are indexed so queries stay fast as data grows.

## How the tables relate (the big picture)

- A **user** owns many **creatures**. Each creature is of one **species**.
- Petting a creature writes a row in **pettings** (who petted which creature,
  when) — this drives cooldowns, currency earning, and the "times petted" count.
- **exploration_visits** tracks how many clicks a user has spent in an area.
- **items** define things that exist; **inventory** records which user owns which
  items; **shops** + **shop_items** define what is for sale and where.
- **rate_limit_hits** records attempts at protected actions so they can be throttled.
- Later milestones added **who a creature is** (traits, badges, the pound), the
  **library** (alternate forms, Gazette articles), **themes**, **company**
  (friends, blocks, postcards, bottles), and the panel's own tables (settings,
  texts, CSS history, bug reports) — each described in order below.

---

## Tables

### `users` — player accounts
Everything hangs off a user. We keep the currency balance and the "last adopted"
time directly on the user because there is exactly one of each per user.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `username` | varchar(30), unique, NOT NULL | Login/display name. |
| `email` | varchar(255), unique, NOT NULL | Account identity (no email is sent in the demo). |
| `password_hash` | varchar(255), NOT NULL | From `password_hash()` — never plain text. |
| `currency_balance` | int unsigned, NOT NULL, default 0 | The single in-game currency (gems). Earned by petting **other people's** creatures, spent on treats and new creatures. |
| `last_adopted_at` | datetime, NULL | Unused since M2. Powered the once-per-day adoption limit, which was retired when creatures moved into the shop. Left in place rather than dropped: it is harmless, and a column removal is not worth a migration nobody needs. |
| `last_login_at` | datetime, NULL | When they last logged in. |
| `theme_id` | int unsigned, NULL, FK → `themes.id` (SET NULL) | The theme they chose (M4). NULL = the site's own look, or the panel's default theme. |
| `welcome_dismissed_at` | datetime, NULL | When they said "don't show me this again" to Ruinily's welcome (M7). NULL = still shown. |
| `created_at` | timestamp, NOT NULL | Set on creation. |

### `species` — the kinds of creature (content)
Species are content, stored as data so a new one is a new row, not new code.
**Images are not stored here** — each species' animated images are found by the
convention `public/assets/creatures/{slug}/{stage}.gif` (e.g. `.../mossling/baby.gif`).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(50), unique, NOT NULL | Machine name + image folder name, e.g. `mossling`. |
| `name` | varchar(60), NOT NULL | Display name. |
| `flavour_text` | text, NULL | Optional description (used from C.2). |
| `is_starter` | boolean, NOT NULL, default false | Can be a new player's starter (A.2). |
| `is_adoptable` | boolean, NOT NULL, default true | Can a player come to own one? Since M2 this decides what the shop sells and what exploring can turn up. |
| `favourite_item_id` | int unsigned, NULL, FK to items | The treat this species adores: worth double. ON DELETE SET NULL, so retiring an item quietly leaves no favourite. A foreign key rather than a slug (unlike the exploration loot tables) because this is a relation between two tables and the database can enforce that the item exists. |
| `disliked_item_id` | int unsigned, NULL, FK to items | The treat it would rather not: worth a quarter, floor of 1. **Never a punishment** — the creature eats it, cheers up a little, and pulls a face. |
| `gem_price` | int unsigned, NOT NULL, default 0 | What one costs in the shop (M2). There is deliberately **no `shop_creatures` table**: there is one place to get a creature and a species has one price, so a join table would model many-shops-many-species, which this site does not have. |
| `created_at` | timestamp, NOT NULL | |

### `creatures` — the animals players collect
The heart of the game. **Growth is derived, not stored:** only `xp` is kept; the
creature's level and life stage (baby/juvenile/adult) are calculated from `xp`
using thresholds in config (B.2). This keeps a single source of truth.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `owner_id` | int unsigned, **NULL** since M3, FK → `users.id` (CASCADE) | The owner. NULL = at the pound (see "The pound, and who a creature is" below). |
| `species_id` | int unsigned, NOT NULL, FK → `species.id` (RESTRICT) | Which kind. |
| `form_id` | int unsigned, NULL, FK → `species_forms.id` (SET NULL) | Which alternate form it wears (M5). NULL = the default look. |
| `name` | varchar(40), NOT NULL | The owner's chosen name. |
| `xp` | int unsigned, NOT NULL, default 0 | Growth's single source of truth. |
| `happiness` | int unsigned, NOT NULL, default 80 | 0–100. How happy the creature was **at `happiness_at`** — not necessarily now. Rises when petted or fed; fades with time down to `gameplay.mood.happiness_floor` and no further. |
| `happiness_at` | timestamp, NULL | When `happiness` was last set. |
| `energy` | int unsigned, NOT NULL, default 100 | 0–100. How rested the creature was **at `energy_at`**. Spent by interacting, recovers on its own. Never blocks anything. |
| `energy_at` | timestamp, NULL | When `energy` was last set. |
| `last_played_at` | timestamp, NULL | When this creature last had a GAME that earned it a bonus. NULL means never. Gates the play reward only — a creature on cooldown still plays. A column and not a table, because only the owner can play and nothing displays a play count, so there is exactly one question to answer. |

| `bio` | text, NULL | Owner-written bio (C.1). |
| `is_public` | boolean, NOT NULL, default true | Whether logged-out visitors can see it (B.6). |
| `last_interacted_at` | datetime, NULL | Last petted, fed or played with. |
| `pound_entered_at`, `pound_previous_owner_id`, `is_frozen`, `shown_stage` | see below | The pound and freezing (M3) — described under "The pound, and who a creature is". |
| `created_at` | timestamp, NOT NULL | |

Indexes: `is_public` and `created_at` (for public browse / newest-first lists),
plus the automatic indexes on the two foreign keys.

**Moods are derived, like growth.** The two readings are values plus the moment
each was true; `MoodCalculator` works out the current figure on read. Nothing runs
on a schedule to decay them — a scheduled job that failed would silently freeze
every creature in the game. The creature queries ask the **database** for the
elapsed time (`TIMESTAMPDIFF`) so the clock that wrote the timestamp is the clock
that measures the gap.

Before M2, `happiness` was an unbounded tally of pets. That count was never lost:
"times petted" has always been answered from the `pettings` table, which is where
the question belongs.

**Which code writes what.** `CreatureRepository` reads creatures, makes them, and
edits their words. The mood columns and `last_played_at` are written only by
`CreatureInteractions`, whose five methods must run inside a transaction holding
the creature's row lock — read that class before changing any of them.

### `pettings` — one row each time a creature is petted
This event log answers questions a single timestamp could not: *has this person
petted this creature recently?* (cooldown), *how many times has this person been
paid lately?* (the daily gem cap), and *how many times has this creature been
petted?* (the displayed count).

Since M2 a petting pays the **person doing the petting**, not the owner, and only
when the creature belongs to somebody else — so the cap query has to join to
`creatures` to know whose it was. See `PettingRepository::countPaidPetsBy`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `creature_id` | int unsigned, NOT NULL, FK → `creatures.id` (CASCADE) | Creature petted. |
| `actor_user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | Who petted it. |
| `created_at` | timestamp, NOT NULL | When. |

Index: `(creature_id, actor_user_id, created_at)` — makes the cooldown lookup fast.

### `exploration_visits` — per-area click tracking
Remembers, per user and per area, how many clicks were used in the current window
and when that window began, so the per-visit limit can be enforced and refreshed (B.5).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | |
| `area_slug` | varchar(50), NOT NULL | Which area (areas are content/config). |
| `clicks_used` | int unsigned, NOT NULL, default 0 | Clicks spent this window. |
| `window_started_at` | datetime, NOT NULL | When the window began. |

Index: `(user_id, area_slug)`.

### `item_categories` — what kind of thing an item is (content)
Added in M1.2. A category carries the three signals a player uses to recognise an
item at a glance: a colour, an icon, and a word. All three, always — colour alone
excludes colour-blind players, so the name is never optional.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(40), unique, NOT NULL | Machine name, e.g. `dish`. |
| `name` | varchar(40), NOT NULL | What a player reads, e.g. "Dish". |
| `colour_token` | varchar(60), NOT NULL | The **name** of a theme token, e.g. `--category-dish` — never a colour value, so the palette stays in `theme.css` (CLAUDE.md section 8). |
| `icon_key` | varchar(40), NOT NULL | Which drawing in the inline SVG sprite to show. |
| `sort_order` | int unsigned, NOT NULL, default 0 | The order categories appear in. Data, so reordering needs no code. |
| `created_at` | timestamp, NOT NULL | |

The eight seeded categories — ingredient, dish, potion, material, seed, tool,
sticker, badge — are taken from the artist's design document, not invented.
Adding a ninth is one row. See `docs/adding-items.md`.

### `items` — definitions of things that can be owned/sold (content)
Defines what an item *is*. Ownership and sale are separate tables.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(50), unique, NOT NULL | Machine name, e.g. `gold-star-sticker`. |
| `name` | varchar(60), NOT NULL | Display name. |
| `description` | text, NULL | Optional. |
| `price` | int unsigned, NOT NULL, default 0 | Cost in the in-game currency. |
| `sell_value` | int unsigned, NOT NULL, default 0 | What a player gets back for selling one. **Must never exceed `price` for an item a shop offers** — otherwise buy-and-sell is a money loop. 0 means "not sellable" (M1.1). |
| `happiness_bonus` | int unsigned, NOT NULL, default 0 | How much happier a creature is for eating this (M2). |
| `energy_bonus` | int unsigned, NOT NULL, default 0 | How much more rested a creature is for eating this (M2). |
| `category_id` | int unsigned, NOT NULL, FK → `item_categories.id` (RESTRICT) | What kind of thing it is. Replaced the old free-text `type` column in M1.2, so there is one answer rather than whatever somebody happened to type. |
| `created_at` | timestamp, NOT NULL | |

Indexes: unique `slug`, plus the automatic index on the category foreign key.

**What makes something food.** An item is a treat if either bonus above is above
zero — there is deliberately no `is_treat` column, because a flag could disagree
with the effects and then somebody would have to think about which one was right.
`Item::isTreat()` asks the numbers.

**The two numbers.** `price` is what a shop charges; `sell_value` is what a player
gets back. They are separate on purpose, and a shop must always keep a margin
between them — the ceiling lives in `config/config.php` under
`gameplay.economy.maximum_sell_fraction_of_price`, currently 80%. Otherwise
buying and selling in a loop would make currency out of nothing. See
`docs/owned-things.md` rule 3, and `tests/Integration/ItemSellValueTest.php`.

### `inventory` — which items each user owns
A join table between users and items, with a quantity.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | |
| `item_id` | int unsigned, NOT NULL, FK → `items.id` (RESTRICT) | |
| `quantity` | int unsigned, NOT NULL, default 1 | How many owned. |

Unique `(user_id, item_id)` — at most one row per user+item, so `quantity` is reliable.

### `shops` — the shops (one for now)
One shop exists today, but it has its own table (with a single seeded row) so a
second shop later is a data change, not a code change.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(50), unique, NOT NULL | e.g. `general-store`. |
| `name` | varchar(60), NOT NULL | |
| `description` | text, NULL | Optional. |
| `created_at` | timestamp, NOT NULL | |

### `shop_items` — which items each shop offers
A join table between shops and items. The price lives on the item, so a shop's
stock is a simple join.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `shop_id` | int unsigned, NOT NULL, FK → `shops.id` (CASCADE) | |
| `item_id` | int unsigned, NOT NULL, FK → `items.id` (RESTRICT) | |
| `available_from` | date, NULL | Optional start of a seasonal window. NULL = always been on sale. |
| `available_to` | date, NULL | Optional end. NULL = and always will be. |

Unique `(shop_id, item_id)` — a shop offers a given item at most once.

**The two dates are not read by anything yet.** They exist ahead of the shop engine
in M9 because adding a column to a table nobody is using costs nothing, and adding
one later to a table full of live listings does not. NULL in either means "no
limit at that end", so every existing listing behaves exactly as before. When M9
honours them, a listing outside its window is simply not offered — never shown
greyed out with "come back in March", which is a tease this site does not do.

### `rate_limit_hits` — records attempts, so they can be throttled
The rate limiter (arriving with the first protected endpoint, A.1) records a row
each time a protected action is attempted, then counts recent rows to decide
whether the next attempt is allowed.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `action_key` | varchar(50), NOT NULL | Which action, e.g. `login`. |
| `identifier` | varchar(64), NOT NULL | Who, e.g. an IP address or user id. |
| `created_at` | timestamp, NOT NULL | When the attempt happened. |

Index: `(action_key, identifier, created_at)`.

### `guestbook_entries` — visitors signing a creature's guestbook
Added in increment C.4. Each row is one person's signature in one creature's
guestbook.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `creature_id` | int unsigned, NOT NULL, FK → `creatures.id` (CASCADE) | Whose guestbook. |
| `author_user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | Who signed. |
| `message_key` | varchar(60), NOT NULL | Which predefined message they chose. |
| `created_at` | timestamp, NOT NULL | When they first signed. |
| `updated_at` | timestamp, NOT NULL | When the entry was last changed. |

Unique `(creature_id, author_user_id)` — **one entry per person per creature.**
Index `(creature_id, created_at)` for listing a guestbook newest-first.

**Three things about this table are worth understanding:**

- **It stores a message KEY, not message text.** Visitors never type anything; they
  choose from a fixed list in `config/config.php` under `gameplay.guestbook.messages`.
  Storing the short key means the Product Owner can reword a message and every
  entry using it updates instantly, with no database change. It also means no
  user-written text is ever stored — which is what makes the guestbook spam-proof
  by design rather than by filtering.
- **The unique index is the real rule, not just an optimisation.** "One entry per
  person per creature" is enforced by the database itself, so a double-submitted
  form or two simultaneous requests still cannot produce a second row. The service
  layer checks it too, but only so it can give a friendly message.
- **`updated_at` powers the once-a-day change.** It is set when the entry is created
  and refreshed whenever the message is changed; the cooldown
  (`gameplay.guestbook.edit_cooldown_seconds`) is measured from it. MySQL's automatic
  `ON UPDATE` behaviour is deliberately switched off so the value only changes when
  our own code decides it should.

---

### `user_roles` — who is staff, and as what (M2.1)

One row per role a user holds. A join table rather than a column on `users`
because roles are **additive** — the artist can also be an owner. The four
role names (`owner`, `moderator`, `artist`, `coder`) are allow-listed in
`src/Admin/Role.php`; the column is plain text, so adding a role is a code
edit there, not a migration.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | Who holds the role. |
| `role` | varchar(20), NOT NULL | One of the four allow-listed names. |
| `granted_by_user_id` | int unsigned, NULL, FK → `users.id` (SET NULL) | Who granted it; NULL = the CLI bootstrap script. |
| `granted_at` | timestamp, NOT NULL | When. |

- **UNIQUE (`user_id`, `role`)** makes granting idempotent: the same grant
  arriving twice at once is a no-op, never a duplicate row — which keeps the
  "how many owners are left?" count honest (the last-owner guard depends on it).
- **SET NULL, not CASCADE, on `granted_by_user_id`**: deleting the *granter*
  must not delete the *target's* role; the reference is cleared, the role stays.
- Only two things ever write this table: `RoleAssignmentService` (owner-only,
  through the panel) and `bin/grant-role.php` (shell access only).

### `admin_audit_log` — everything staff have done (M2.1)

Who, what, when, before, after. **Append-only by construction**: the
repository (`AuditLogRepository`) has insert and read methods and will never
grow an update or a delete — a test (`AdminEscalationTest`) fails the build
if one appears.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `actor_user_id` | int unsigned, NULL, **no FK** | Who did it; NULL = a CLI script. Deliberately no foreign key: deleting an account must never delete its audit trail. |
| `action` | varchar(50), NOT NULL | Allow-listed in `src/Admin/AuditAction.php`, e.g. `role.granted`. |
| `subject_type` / `subject_id` | varchar(30) NULL / int NULL | What it happened to (polymorphic, like `reports`). |
| `detail_before` / `detail_after` | text, NULL | JSON snapshots of the changed values. |
| `ip` | varchar(45), NOT NULL | Where from (`cli` for scripts). IPv6 fits. |
| `created_at` | timestamp, NOT NULL | When. |

Indexes: (`actor_user_id`, `created_at`) for one person's history;
(`created_at`) for the newest-first audit page.

### `admin_second_factors` — each staff account's authenticator app (M2.1)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `user_id` | int unsigned, NOT NULL, UNIQUE, FK → `users.id` (CASCADE) | One enrolment per account. |
| `totp_secret` | varchar(64), NOT NULL | The shared TOTP secret, base32. Stored plain because the site must *compute* codes from it. Kept out of `users` so `UserRepository` can never accidentally load it. |
| `enrolled_at` | timestamp, NOT NULL | When the app was connected. |

### `admin_recovery_codes` — the way back in when the phone is lost (M2.1)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | Whose codes. |
| `code_hash` | varchar(255), NOT NULL | Hashed like a password — the plain code is shown exactly once, at enrolment. |
| `used_at` | datetime, NULL | Stamped when spent. Single use is enforced atomically: `UPDATE … SET used_at = NOW() WHERE id = ? AND used_at IS NULL`. |
| `created_at` | timestamp, NOT NULL | When issued. |

Index (`user_id`, `used_at`): "this account's unspent codes", checked at the
panel door.

### `art_files` — the site's memory of its uploaded pictures (M2.2)

One row per picture in the art store. The BYTES live on the Railway volume
(a folder locally) behind `ImageStore`; this table records what is there.
Serving (`/art/{name}`) looks a picture up here by name before reading any
byte from disk.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `stored_name` | varchar(70), NOT NULL, UNIQUE | `sha256(bytes)` + extension. Same bytes → same name (a duplicate upload is a no-op); changed bytes → new name (a year-long browser cache can never show a stale picture). |
| `kind` | varchar(20), NOT NULL | sprite / icon / portrait / card / background / library — allow-listed in `src/Art/ArtKind.php`. |
| `original_name` | varchar(100), NOT NULL | The browser's filename, sanitised, for **display only**. Never a path. |
| `type` | varchar(10), NOT NULL | gif / png / webp — decided by reading the bytes, never the name. |
| `width` / `height` | int unsigned, NOT NULL | Pixels, from the file's header. |
| `bytes` | int unsigned, NOT NULL | Size of the stored file. `SUM(bytes)` is the "disk getting full" number. |
| `is_animated` | boolean, NOT NULL | More than one frame. Animated files were stored byte-for-byte (minus metadata blocks); still ones re-encoded. |
| `uploaded_by_user_id` | int unsigned, NULL, FK → `users.id` (SET NULL) | The delete rule (an artist may only delete their own) is checked against this column. SET NULL: an artist leaving must not take the site's sprites with them. |
| `created_at` | timestamp, NOT NULL | When. |

Index (`kind`, `created_at`): the Pictures screen lists by kind, and M2.3
will ask "every sprite".

### The species editor's additions (M2.3)

The `species` table gained: `status` (varchar(10), `'draft'`/`'published'`,
**default `'published'`** — deliberately, so seeds and old fixtures stay
live content; the panel's create path writes `'draft'` explicitly),
`updated_at` (datetime NULL), `lore` (text NULL, for the library milestone),
and `library_art_id` (int unsigned NULL, FK → `art_files` **SET NULL**).
Index on `status`. The acquisition queries (`findAdoptable`,
`findStarters`) require `status = 'published'`; `findById`/`all` do NOT —
players may own creatures of a re-drafted species and those must render.

### `species_stages` — one row per (species, stage)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `species_id` | int unsigned, NOT NULL, FK → `species.id` (CASCADE) | |
| `stage` | varchar(20), NOT NULL | Validated in PHP against config `growth.stage_start_levels` keys. |
| `sprite_art_id` | int unsigned, NULL, FK → `art_files.id` (SET NULL) | The stage's picture in the art store; NULL = the old convention path carries it (the three original species). |
| `blurb` | text, NULL | Words for this stage, shown instead of the flavour text. |

UNIQUE (`species_id`, `stage`) — saves are upserts. Index on
`sprite_art_id`: "which species use this picture?", the art delete-guard's
question.

### `content_versions` — every editor's undo (M2.4; absorbed `species_versions`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `content_type` | varchar(20), NOT NULL | `species` / `item` / `shop` — hard-coded by each editor service, never from a request. |
| `content_id` | int unsigned, NOT NULL | Which one, in that type's own table (polymorphic — PHP guards what an FK cannot, like `reports`). |
| `snapshot` | text, NOT NULL | The whole editable state as JSON — art as REFERENCES, never bytes. A shop's snapshot includes its shelves. |
| `saved_by_user_id` | int unsigned, NULL, FK → `users.id` (SET NULL) | |
| `created_at` | timestamp, NOT NULL | |

Index (`content_type`, `content_id`, `created_at`). One snapshot per save,
pruned to the newest N (config `art.species_versions_kept`, 20 — the one
depth every editor shares). Restoring applies whitelisted fields only and
never touches `status`. The M2.3-era `species_versions` table was copied in
and dropped by migration `20260831130001`.

### The item and shop editors' additions (M2.4)

**`items` gained**: `status` (draft/published, default `'published'` — the
same seeds-stay-live reasoning as species), `updated_at`, `icon_art_id`
(FK → `art_files`, SET NULL — the three-source icon cascade: art-store
icon, else the old `/assets/items/{slug}.png` convention file, else the
category icon). Index on `status`. The shop's listing AND purchase queries
require `status='published'`.

**`shops` gained**: `status` + `updated_at` (same shape), the keeper —
`keeper_name` (60), `keeper_greeting` (300), `keeper_refusal` (150), all
NULL (a keeperless shop renders as shops always did; Ruinily and her
refusal line were seeded verbatim by the migration) — and two art links,
`background_art_id` / `portrait_art_id` (FK → `art_files`, SET NULL).

**`shop_items`' seasonal columns came alive**: `available_from`/`available_to`
(added 2026-08-12) are now honoured by `ShopRepository::findItems()` and
`findSoldItem()` — NULL means always; out of season means simply not
offered, at the page AND at purchase time.

---

### `areas` — the places to explore (content; M2.5)
Areas moved out of config and into the database when the panel's area editor
arrived: a place is content the artist builds, not code. The seeded
whispering-wood was carried over by the migration word for word, so nothing
read differently on deploy day. **The slug is the area's forever-address**
(`/explore/whispering-wood`, and `exploration_visits` has always referenced
areas by slug) — minted once, never editable, and there is no delete:
retiring an area is taking it back to `draft`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(50), unique, NOT NULL | The address; see above. |
| `name` | varchar(60), NOT NULL | Display name. |
| `description` | varchar(500), NULL | The line under the name on the area page. |
| `background_art_id` | int unsigned, NULL, FK → `art_files.id` (SET NULL) | The scene painting, from the art store (Background kind). NULL renders the painted-glow placeholder. |
| `status` | enum('draft','published'), NOT NULL, default 'published' | Default 'published' so the seed stays live; the panel writes 'draft'. Player reads filter on it; the panel sees everything. |
| `updated_at` | datetime, NOT NULL | Touched by every editor save. |
| `created_at` | timestamp, NOT NULL | |

### `area_spots` — where a scene can be searched (M2.5)
The clickable spots, one row each, positioned by percentage across the
scene (0–100 from the left, 0–100 from the top). Replaced wholesale on
every spots save — a set this small (twenty at most) isn't worth diffing.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `area_id` | int unsigned, NOT NULL, FK → `areas.id` (CASCADE) | Deleted with its area. |
| `x` | tinyint unsigned, NOT NULL | Percent from the left. |
| `y` | tinyint unsigned, NOT NULL | Percent from the top. |
| `position` | tinyint unsigned, NOT NULL | Keeps the artist's ordering stable. |

### `loot_entries` — what searching an area finds (M2.5)
The weighted reward table, one row per line of the panel's plain-line loot
field. **Items are referenced by foreign key here** (unlike the old config
tables, which used slugs): both sides live in the database now, so the
database can enforce that the item exists — and RESTRICT means an item on
a loot table refuses deletion, the same guard the editor explains in words.
The parser makes crash-the-roll tables unwritable (no empty tables, every
weight ≥ 1), so the reward roll never divides by zero.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `area_id` | int unsigned, NOT NULL, FK → `areas.id` (CASCADE) | Deleted with its area. |
| `type` | enum('nothing','creature','item'), NOT NULL | What is granted. |
| `item_id` | int unsigned, NULL, FK → `items.id` (RESTRICT) | Only for type 'item'. |
| `weight` | smallint unsigned, NOT NULL | Relative chance, ≥ 1. |
| `message` | varchar(200), NOT NULL | What the player reads. |
| `position` | smallint unsigned, NOT NULL | The table's line order. |

### Moderation's additions (M2.7)

**`users` gained** `muted_until` and `suspended_until` (both datetime,
NULL). NULL is good standing; a time in the past has expired on its own —
every timed state has a built-in end, so a forgotten mute cannot quietly
become forever. Muting pauses NEW free text only (bios, about texts,
names, guestbook signing — enforced where those forms land, via
`MutedVoice`); suspension refuses login and ends a live session at its
next request (checked in the front controller).

### `moderation_notices` — the one-way channel (M2.7)
Official notices to players: a card at the top of every page until
acknowledged. `kind` is a TEMPLATE KEY (the words live in
`ModerationNotice`, written once and calmly) — never typed text, so even
staff have no free-text channel to a player. `detail` is machine-formatted
(today: the until-date on timed states). No staff id on the row: the
player is told what happened, not who did it; the audit log carries who.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | Who it is for. |
| `kind` | varchar(40), NOT NULL | The template key, e.g. `bio_removed`. Unknown keys render a safe generic card. |
| `detail` | varchar(60), NULL | Machine-formatted detail (a date). Never typed input. |
| `created_at` | timestamp, NOT NULL | |
| `read_at` | datetime, NULL | When "I've read this" was pressed. NULL = the card still shows. Kept, not deleted, so account history can show what was said. |

Index: `(user_id, read_at)` — the every-page "any unread notices?" query.

### `site_settings` — the panel's overrides on config (M2.8)
Config stays where every tunable value is DEFINED, explained and
defaulted; this table holds only what the panel CHANGED. A missing row
means "the built-in value", so deleting a row is the reset button.
`setting_key` is a catalog key from the fixed allow-list in
`SettingsCatalog` — never a config path; unknown keys and out-of-bounds
values in this table are ignored on read, so the table cannot smuggle
what the settings form refuses.

| Column | Type | Notes |
| --- | --- | --- |
| `setting_key` | varchar(60), PRIMARY KEY | The catalog key, e.g. `xp_per_level`. |
| `value` | text, NOT NULL | JSON — a number, a bool, or a word list ride the same column. |
| `updated_at` | datetime, NOT NULL | When it was last changed. |

### The pound, and who a creature is (M3)

Migration `20261103140001_create_character.php`. The pound is built on one
idea: **a creature at the pound belongs to nobody.** `creatures.owner_id` became
nullable for it. Every "is this yours?" check in the codebase compares
`owner_id` with the viewer, so a NULL owner makes all of them refuse everyone —
without each check having to learn about the pound.

New columns on `creatures` (summarised in the table above):

| Column | Type | Notes |
| --- | --- | --- |
| `owner_id` | int unsigned, **NULL** since M3 | NULL = at the pound. |
| `pound_entered_at` | datetime, NULL | When it arrived at the pound (NULL = not there). Indexed: the pound page lists by arrival. |
| `pound_previous_owner_id` | int unsigned, NULL | Who brought it: the one person who may reclaim it during the grace period, and the one person who may never adopt it back afterwards. Indexed. |
| `is_frozen` | boolean, NOT NULL, default false | Frozen at a life stage (M3.2b): XP still counts, the stage shown does not move. |
| `shown_stage` | varchar(20), NULL | Overrides the stage worked out from `xp`. While catching up after an unfreeze it walks forward one stage per growth tick, then clears. |

### `traits` — personality (earned) and shelter/gift (given) traits (M3)
Starter rows are inserted by the migration and are editable in the panel
(`/admin/character`). A trait may have exactly **one** mechanical effect
(`xp_bonus_percent`), by design — traits are flavour first.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(40), unique, NOT NULL | Stable name used in code. |
| `name` | varchar(40), NOT NULL | What players see. |
| `description` | varchar(200), NOT NULL | One sentence about it. |
| `tier` | varchar(10), NOT NULL | `earned` (by living), `shelter` (given on leaving the pound), `gift` (a chance reward on adoption). |
| `earn_kind` | varchar(20), NULL | Earned traits: what earns it (`petted`, `level`, `age_days`). |
| `earn_threshold` | int unsigned, NULL | Earned traits: how much of it. |
| `chance_percent` | int unsigned, NOT NULL, default 0 | Gift traits: the chance of receiving it. |
| `xp_bonus_percent` | int unsigned, NOT NULL, default 0 | The one effect a trait may have. |

### `creature_traits` — which creature has which trait (M3)
Composite primary key (`creature_id`, `trait_id`): a creature can never hold the
same trait twice, and `INSERT IGNORE` is the whole "give it once" rule.

| Column | Type | Notes |
| --- | --- | --- |
| `creature_id` | int unsigned, PK part, FK → `creatures.id` (CASCADE) | |
| `trait_id` | int unsigned, PK part, FK → `traits.id` (CASCADE) | |
| `how` | varchar(120), NOT NULL | In words, how it came by this ("after its first 50 pets"). |
| `gained_at` | timestamp, NOT NULL | |

### `ladder_rungs` — level titles and the friendly-age ladder (M3)
Two ladders in one table, told apart by `ladder` (`level_title`, `age`). The
label for a creature is the highest rung whose `threshold` it has reached.
Unique on (`ladder`, `threshold`).

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `ladder` | varchar(20), NOT NULL | Which ladder. |
| `threshold` | int unsigned, NOT NULL | A level, or a number of days. |
| `label` | varchar(60), NOT NULL | "Sprout", "about a week old". |

### `themes` — selectable looks (M4, extended by the site-look migration)
A named set of colour-token overrides plus, since the site-look migration, a
`style` of allow-listed choices. Draft until published from the panel, and
**the panel refuses to publish a theme that fails any contrast rule** in
`ThemeTokens`. Three starter themes are inserted (Moonlit, Rosehip, Easy
reading). `users.theme_id` is the player's choice.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(40), unique, NOT NULL | |
| `name` | varchar(40), NOT NULL | |
| `tokens` | text, NOT NULL | JSON: only the tokens that differ from the site's own, only ones `ThemeTokens` allows. |
| `easy_reading` | boolean, NOT NULL, default false | Switches on the dyslexia-friendly type. |
| `status` | varchar(10), NOT NULL, default `draft` | `draft` or `published`. |
| `style` | text, NULL | JSON of allow-listed choices (fonts, corners, frames, glow, drifting symbols) — every allowed value is listed in `ThemeStyle`. NULL = the site's own look. |
| `background_art_id` | int unsigned, NULL, FK → `art_files.id` (SET NULL) | A page background tile from the art store. |
| `banner_art_id` | int unsigned, NULL, FK → `art_files.id` (SET NULL) | A banner instead of the painted logo. |
| `created_at` | timestamp, NOT NULL | |

### `species_forms` and `species_form_stages` — alternate looks for a species (M5)
A form is an alternate look ("Frost Foxlen"); which species get one is the
artist's call, made in the panel. `creatures.form_id` says which form a creature
wears (NULL = the default form; SET NULL on delete, so removing a form turns its
creatures back into the default look instead of deleting anybody's creature).

`species_forms`: `id`, `species_id` (FK → `species.id`, CASCADE), `slug`
varchar(60), `name` varchar(60), `blurb` text NULL, `status` varchar(10) default
`draft`, `created_at`. Unique on (`species_id`, `slug`).

`species_form_stages`: `id`, `form_id` (FK → `species_forms.id`, CASCADE),
`stage` varchar(20), `sprite_art_id` (FK → `art_files.id`, SET NULL). Unique on
(`form_id`, `stage`). A stage with no sprite here falls back to the species'
default sprite, so a half-drawn form never shows a hole.

### `articles` — the Felkyo Gazette and site updates (M5; `kind` added later)
Newspaper pieces about the NPCs, authored in the panel, read in the library. An
update (the little site blog) is written exactly like a Gazette article, so it
IS one, told apart by `kind`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(80), unique, NOT NULL | Appears in the address. |
| `kind` | varchar(10), NOT NULL, default `gazette` | `gazette` (stories, in the library) or `update` (news about the site, at `/updates`). |
| `title` | varchar(120), NOT NULL | |
| `summary` | varchar(300), NULL | |
| `body` | text, NULL | |
| `art_id` | int unsigned, NULL, FK → `art_files.id` (SET NULL) | The picture beside it. |
| `status` | varchar(10), NOT NULL, default `draft` | |
| `published_at` | datetime, NULL | |
| `created_at` | timestamp, NOT NULL | |

Indexes: (`status`, `published_at`) and (`kind`, `status`, `published_at`) —
each list is "published, of this kind, newest first".

### Company: `friends`, `blocks`, `postcard_designs`, `postcards`, `bottles`, `daily_claims` (M6)
Players never type words to each other here: every card and bottle carries a
**fixed** message the project wrote.

- **`friends`** — a bookmark: "I like visiting this person". One-way, no consent
  needed, unlocks nothing — so, like bookmarking a page, it needs no approval.
  Primary key (`user_id`, `friend_user_id`), both FK → `users.id` (CASCADE);
  `created_at`.
- **`blocks`** — "nothing from this person reaches me": no cards, and their
  bottles never wash up on my shore. Primary key (`user_id`, `blocked_user_id`),
  both FK → `users.id` (CASCADE); `created_at`. Indexed on `blocked_user_id`
  because "has anyone blocked ME?" is asked when sending.
- **`postcard_designs`** — the cards on sale at the post office: `id`, `name`
  varchar(60), `message` varchar(200) (the fixed words), `art_id` (FK →
  `art_files.id`, SET NULL), `gem_price` int unsigned default 2, `status`
  varchar(10) default `draft`. Five starter cards are inserted.
- **`postcards`** — a card sent: `id`, `sender_user_id`, `recipient_user_id`
  (both FK → `users.id`, CASCADE), `design_id` (FK → `postcard_designs.id`,
  CASCADE), `sent_at`. Indexed on (`recipient_user_id`, `sent_at`): the
  mantelpiece lists a recipient's cards newest first.
- **`bottles`** — a message in a bottle: `id`, `sender_user_id` (FK → `users.id`,
  CASCADE), `message_key` varchar(40) (a key into the fixed catalogue in config),
  `sent_at`, `finder_user_id` (FK → `users.id`, SET NULL), `found_at`. Indexed
  on (`finder_user_id`, `sent_at`): the shore looks for bottles still adrift.
- **`daily_claims`** — house pattern 2, "once a day": primary key (`user_id`,
  `action_key` varchar(30), `action_date` date), FK → `users.id` (CASCADE). The
  database itself refuses a second bottle sent or found on the same day.

### `badges`, `creature_badges`, `player_badges` — keepsake badges (M8, pulled forward)
A badge is a name, a few words, a picture (art kind "badge"), and who it is for.
`awarded_to` = `creature` means it is **earned automatically** by a creature that
has been in Felkyo for `kept_days` days — only ever earned, never lost, because
absence is not punished. `awarded_to` = `player` means a special badge
("Founder") the owner gives from the gift desk; never earned by playing.

`badges`: `id`, `slug` varchar(40) unique, `name` varchar(40), `description`
varchar(200), `art_id` (FK → `art_files.id`, SET NULL), `awarded_to`
varchar(10), `kept_days` int unsigned NULL. Four starter badges are inserted.

`creature_badges`: primary key (`creature_id`, `badge_id`), both CASCADE;
`earned_at`. `player_badges`: primary key (`user_id`, `badge_id`), both CASCADE;
`reason` varchar(200) NULL, `given_at`. The composite keys mean a badge can never
be given twice to the same creature or player.

### `welcome_steps` — Ruinily's first-run welcome (M7)
What she says, in order, each with an optional link to the place she's talking
about; edited in the panel (`/admin/welcome`). `users.welcome_dismissed_at` is
set the moment a player says "don't show me this again" (or finishes), and the
bubble never returns.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `position` | int unsigned, NOT NULL, indexed | The order she says them in. |
| `words` | varchar(300), NOT NULL | |
| `link_href` | varchar(120), NULL | Where the step points. |
| `link_label` | varchar(60), NULL | What the link says. |

### `bug_reports` — "Something broken?" (birthday sprint)
A logged-in player tells us something on the site is broken, from the page
where it happened. **Kept apart from `reports`** on purpose: that table is for
people and their words (moderation); a bug report points at a *page* and is
read by whoever fixes the code, not by a moderator. Text only — no screenshot
uploads, because uploads are the riskiest thing a site can accept.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `reporter_user_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | Deleting an account takes the person's own words with it. |
| `page_path` | varchar(300), NOT NULL | The address they were on, filled in automatically and checked to be a path on this site. |
| `browser` | varchar(300), NOT NULL, default '' | The browser's own description of itself (user agent). |
| `screen_width` | int unsigned, NULL | How wide their window was — for layout bugs. |
| `description` | text, NOT NULL | What happened, in their words. |
| `status` | varchar(10), NOT NULL, default `open` | `open`, `fixed` or `closed`. |
| `handled_by_user_id` | int unsigned, NULL, FK → `users.id` (SET NULL) | Who ticked it off. |
| `handled_at` | datetime, NULL | When. |
| `created_at` | datetime, NOT NULL | |

Index (`status`, `created_at`) serves the panel's only query: one status,
newest first.

### `custom_css_versions` — the fine-tuning CSS box's undo history (birthday sprint)
Append-only: every save (and every restore) adds a row; nothing is ever
overwritten. The CSS the site actually prints is the `site_settings` row
`look.custom_css` (so reading it costs no extra query); this table is what "go
back to an older version" reads from. The site-wide look choices (the default
theme, section order, the announcement line) are likewise rows in
`site_settings` under keys starting with `look.` — no new table needed.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `css` | text, NOT NULL | The CSS as saved (checked by `CustomCssRules` on save and again on print). |
| `note` | varchar(120), NOT NULL, default '' | What this version was for. |
| `saved_by_user_id` | int unsigned, NULL, FK → `users.id` (SET NULL) | |
| `saved_at` | datetime, NOT NULL | |

### `site_texts` — the site's words, as changed in the panel (birthday sprint)
**Only changes are stored.** Every sentence's original lives in the template
that shows it (`$this->t('key', 'The original words')`). A row here replaces
it; deleting the row is "back to the original". So an empty table means the
site reads exactly as written, and nothing can go missing. The whole table is
read in one query per page — it holds only the changed sentences, so it stays
small. Note the primary key is `text_key`, not an `id`: one row per sentence.

| Column | Type | Notes |
| --- | --- | --- |
| `text_key` | varchar(120), PRIMARY KEY | Which sentence, e.g. `shore.walk-the-tideline`, or `message.<hash>` for a message the code sends. |
| `body` | text, NOT NULL | The words to show instead. |
| `original` | text, NULL | Messages only: the pattern they replace (like `Moved "%s" up.`), so a message on its way to the page can be recognised. NULL for template texts. |
| `updated_by_user_id` | int unsigned, NULL, FK → `users.id` (SET NULL) | Who changed it last. |
| `updated_at` | datetime, NOT NULL | |

## What is intentionally NOT here (yet)

These are future extensions for the successor to build on this foundation; they
are deliberately left out to keep the core small (build plan section 6):

- Trading, player-run shops, multiple themed shops.
- Complex item effects, crafting.
- A database sessions table — the app uses PHP's built-in file sessions.

## How to change the schema

1. Create a new migration:
   `C:\xampp\php\php.exe vendor/robmorgan/phinx/bin/phinx create YourChangeName`
2. Write the change in that migration file (see the existing ones as examples).
3. Apply it to both databases:
   `... phinx migrate -e development` and `... phinx migrate -e testing`.
4. Update this document to match.
