# The birthday sprint — M3, M4, M5 and most of M6 (2026-11-03)

**Local branch `birthday`. Never pushed. Not on the live site.**

Katharina asked for as much of the plan as possible before Monday 2026-11-09,
as a birthday present, with the usual per-increment plans, sign-offs and full
test matrices set aside until afterwards. This note records what was built,
what was deliberately skipped, and what must happen before any of it goes live.

## Database: seven new migrations

Run `C:\xampp\php\php.exe vendor/bin/phinx migrate` (and `-e testing`):

| Migration | What it adds |
| --- | --- |
| `20261103120001_create_library` | alternate forms, `creatures.form_id`, Gazette articles |
| `20261103140001_create_character` | the pound columns, **`creatures.owner_id` becomes nullable**, traits, ladders |
| `20261103160001_create_themes` | themes, `users.theme_id`, three starter themes |
| `20261103180001_create_company` | friends, blocks, postcards, bottles, daily claims |
| `20261104090001_add_article_kind` | site updates (articles of kind `update`) |
| `20261104120001_create_badges` | keepsake badges (creature: by days kept; player: given by the owner) |
| `20261104140001_create_welcome` | Ruinily's first-run welcome, `users.welcome_dismissed_at` |

Demo words, a first Gazette article and three creatures waiting at the pound:
`C:\xampp\php\php.exe vendor/bin/phinx seed:run -s DemoLibrary` (only fills
gaps; never overwrites anything written in the panel).

## What exists now

**M5 — the library** (`/library`)
- A shelf with a book for each species. Each species page has its stages and blurbs, its published alternate forms, and a story book you open by clicking the cover (`<details>`, CSS-only animation).
- **Alternate forms:** panel → species → "Alternate forms". Each form has its own sprite for each stage and falls back to the default sprite for any stage it doesn't have. New creatures have a 10% chance (`gameplay.forms.birth_chance_percent`) of being born in a published form.
- **The Felkyo Gazette:** panel → "The Gazette". Articles are plain text, and a blank line starts a new paragraph.

**M3 — the pound, and who a creature is**
- `/pound`: a creature handed in rests for 3 days (`gameplay.pound.grace_days`). During that time its old owner can reclaim it, and nobody can adopt it. After that, anyone except the old owner can adopt it. A creature at the pound has **no owner** (`owner_id` NULL), so every existing ownership check refuses everyone.
- **Traits:** earned ones (petted N times, level N, N days old), shelter ones (1–2 on adoption) and gift ones ("grateful", 30% chance, +10% XP). The XP bonus is the only mechanical effect a trait has, and it's capped at 25%.
- **Freezing at a stage:** XP still counts while frozen. After unfreezing, the creature catches up one stage per growth tick (`CreatureInteractions::stepCatchUp`).
- **Level titles and friendly ages:** panel → "Traits & titles", where both lists are edited as lines.

**M4 — themes** (`/themes`, linked from your own player page)
- Moonlit, Rosehip, and **Easy reading** (the Lexend typeface with roomier spacing, M4.3).
- Panel → "Themes": colour pickers with a live readability list and preview. The server refuses to publish a theme that fails any rule in `ThemeTokens::RULES`.

**M6 — company** (`/friends`, `/post-office`, `/shore`)
- Friends are bookmarks only and unlock nothing. Blocking stops cards and bottles from that person and hides their old cards from your mantelpiece.
- **Postcards:** fixed words, sendable to anyone, paid for in gems, capped at 10 a day. They show on the recipient's profile mantelpiece.
- **Bottles:** one sent and one found per day. The finder is random, and a walk that finds nothing doesn't use up your day. The message list is in config and carries its safety note.
- **Share box:** on every creature page. Set `APP_URL` in `.env` on the live server.

## Deliberately NOT done (yet)

- **M3.3 reputation.** The plan says to agree on how harsh it should be first.
- **Tests for all of the above.** Only the existing suite was kept green; one pre-existing art-upload test (`ArtStoreTest`) fails on this machine and is unrelated. Every controller still has its CSRF, rate-limit and ownership checks, and every query is prepared.
- **The security review, Skerro's front-end pass, the AdminGateTest route matrix** for the new admin routes, and the `docs/` guides.

## Before this goes live

1. Write the tests: every permission boundary refused, every race (adopt, reclaim, find).
2. Add the new admin routes to the AdminGateTest matrix.
3. Do a security review of the pound (nullable `owner_id`!) and the post office.
4. Run the seven migrations on production, and say so in the handover.

## Added on day two (2026-11-04)

- Skerro's fix list: stars instead of the leaf, site updates on the home page (`/updates`), red petting hearts, favourite treats ×4, visible secondary-button borders, centred log-in button on phones, species and item names linking to the library (new item pages: where to find it), shop description above the keeper's greeting, deleting species/items for good (draft only, typed name, nobody loses out), avatar uploads.
- Panel: postcard editor, uploading many pictures at once, the owner-only gift desk (gems, items, a creature, special badges; capped, reason required, audit-logged), badge editor, editing Ruinily's welcome.
- Keepsake badges, Ruinily's first-run welcome (M7), reporting cards and bottles (M6.6: only the recipient / finder can report).
- Still waiting on Skerro's screenshots for: the About Me "one line", "centre the petted text".
