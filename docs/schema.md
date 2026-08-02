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
| `currency_balance` | int unsigned, NOT NULL, default 0 | The single in-game currency. |
| `last_adopted_at` | datetime, NULL | Powers the once-per-day adoption limit (B.4). |
| `last_login_at` | datetime, NULL | When they last logged in. |
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
| `is_adoptable` | boolean, NOT NULL, default true | Can appear in adoption/exploration pools (B.4, B.5). |
| `created_at` | timestamp, NOT NULL | |

### `creatures` — the animals players collect
The heart of the game. **Growth is derived, not stored:** only `xp` is kept; the
creature's level and life stage (baby/juvenile/adult) are calculated from `xp`
using thresholds in config (B.2). This keeps a single source of truth.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `owner_id` | int unsigned, NOT NULL, FK → `users.id` (CASCADE) | The owner. |
| `species_id` | int unsigned, NOT NULL, FK → `species.id` (RESTRICT) | Which kind. |
| `name` | varchar(40), NOT NULL | The owner's chosen name. |
| `xp` | int unsigned, NOT NULL, default 0 | Growth's single source of truth. |
| `happiness` | int unsigned, NOT NULL, default 0 | Interaction stat; a simple counter (no decay). |
| `bio` | text, NULL | Owner-written bio (C.1). |
| `is_public` | boolean, NOT NULL, default true | Whether logged-out visitors can see it (B.6). |
| `last_interacted_at` | datetime, NULL | Last petted; drives the cooldown. |
| `created_at` | timestamp, NOT NULL | |

Indexes: `is_public` and `created_at` (for public browse / newest-first lists),
plus the automatic indexes on the two foreign keys.

### `pettings` — one row each time a creature is petted
This event log answers questions a single timestamp could not: *has this person
petted this creature recently?* (cooldown, B.1), *how many times has this person
petted lately?* (anti-spam currency cap, B.7), and *how many times has this
creature been petted?* (the displayed count). A petting earns the **owner**
currency only when the actor is someone else.

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

### `items` — definitions of things that can be owned/sold (content)
Defines what an item *is*. Ownership and sale are separate tables.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | int unsigned | Primary key. |
| `slug` | varchar(50), unique, NOT NULL | Machine name, e.g. `gold-star-sticker`. |
| `name` | varchar(60), NOT NULL | Display name. |
| `description` | text, NULL | Optional. |
| `price` | int unsigned, NOT NULL, default 0 | Cost in the in-game currency. |
| `type` | varchar(30), NOT NULL | Grouping label for the inventory page (B.8). |
| `created_at` | timestamp, NOT NULL | |

Indexes: unique `slug`, and `type` (for grouping).

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

Unique `(shop_id, item_id)` — a shop offers a given item at most once.

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

---

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
