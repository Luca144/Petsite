# Felkyo v2 — Coding Rules for Claude Code

This file is read by Claude Code at the start of every session. **Everything in this file is a rule, not a suggestion.** If a task would require breaking a rule, stop and ask first — do not break the rule silently.

The team is small and mostly composed of coding newcomers (the Product Owner is an artist; the Lead Developer is learning as she goes). The #1 priority is **code that is readable AND performant**. Readability and smart architecture are co-equal: code must be understandable so it can be maintained, *and* it must be well-structured so it can run efficiently. If you must choose, break the choice by asking: "Does this make the code significantly slower or unmaintainably complex?" If no to both, readability wins. If yes to either, find the balance.

---

## 1. The golden rule

**Write code as if the person reading it has never seen PHP before, and you are responsible for their understanding.**

If you catch yourself writing something clever, rewrite it as something obvious. If you catch yourself writing something dense, add comments that explain what and why. If you catch yourself skipping a comment because "it's obvious," add the comment anyway — "obvious" is relative to experience.

**Language: everything is in English.** All user-facing text on the site (page content, button labels, messages, error text, the shopkeeper's lines, empty states) is in English. All documentation, code comments, commit messages, and the developer/setup/schema/deployment guides are in English too. Keep user-facing copy as data/config (not hardcoded in logic) so it can be translated later if ever needed — but for now, English throughout.

**Naming — use these terms consistently.** The product is called **Felkyo Creatures** (the world can be referred to as "Felkyo"). The animals players collect are called **creatures**, never "pets" — in all user-facing text *and* in the domain/database naming. The main table is `creatures`, the entity is a creature, the page showing one is the **creature page**. The one exception is the interaction verb: petting a creature keeps the verb "pet"/"petted" (e.g. a "Pet [name]" button, a "times petted" count) — that's the action, while "creature" is the thing. So: you *pet* a *creature*.

---

## 2. Comments — educational, not decorative

Every non-trivial block of code has a comment explaining **what it does and why it does it**. "What" alone is not enough. "Why" is what newcomers need to understand the system.

**Good comment:**
```php
// We hash the user's ID together with the pet's name to deterministically
// pick a personality trait. Hashing means every pet with the same owner and
// name will always get the same personality — so personality feels "baked in"
// rather than random. We use SHA-256 because it's built into PHP and fast.
$hash = hash('sha256', $userId . $petName);
```

**Bad comment:**
```php
// Hash the string
$hash = hash('sha256', $userId . $petName);
```

**Comments apply equally to PHP, JavaScript, CSS, and SQL.** A tricky CSS selector or a multi-join SQL query deserves the same explanation a PHP function would.

**File-level docblocks** at the top of every file explain what the file is for, who uses it, and anything important to know when modifying it. Include `@package`, purpose, and a brief "how this fits into the bigger picture" paragraph.

**Do not write comments that are just restating the code.** `// Loop through users` above `foreach ($users as $user)` is noise. Explain why the loop exists and what it accomplishes.

---

## 3. Planning and performance architecture

Every increment deserves upfront thinking about structure. Before writing code, Claude should:

1. **Think about the data flow.** How much data moves where? Are there N+1 queries hiding? Could a JOIN solve this? Could caching help? Write this down (in your thinking, not the code).

2. **Structure for maintainability first, then optimization.** The repository pattern, the service layer, the separation of concerns — these exist to make the code survivable *and* to make performance problems obvious when they arrive. Well-layered code is easier to optimize later than tangled code.

3. **Optimize where it genuinely matters.** NOT "every line must be optimal" — that way lies unreadable code and premature complexity. Instead: 
   - Database queries (the biggest bottleneck): always think about indexes, JOIN strategy, what columns are fetched
   - Hot paths (code that runs per request, per creature, per click): worth a second thought
   - External APIs (every call is slow): cache, batch, or restructure to avoid them
   - Asset loading (CSS, JS, images): minimize and serve efficiently
   
   But NOT: whether a loop or a functional pipeline is 0.001ms faster. Not: whether a local variable gets garbage-collected sooner. Those are noise.

4. **Name the performance goal.** If an optimization changes the code, ask: "What would break if I removed this? Would the feature be unusable, or just 2ms slower?" If it's the latter, plain code wins. If it's the former (e.g., "without this JOIN we'd load 1000 extra queries"), comment it clearly and keep it.

5. **When you plan a substantial feature, say how it scales.** "This works for 100 creatures per player. If it needs to scale to 10,000, we'd need [caching/pagination/denormalization]." Don't build for 10,000 today, but don't surprise people later either.

---

## 4. File size and structure

Long files are hard to navigate and hard to understand. These limits exist to force you to think about what each file is responsible for. Rules:

- **PHP class files: max 300 lines** (flexible if a single, well-named responsibility genuinely needs more). If you're approaching this, the class is probably doing too much. Split it.
- **Template files: max 200 lines.** If a template is longer, extract partials.
- **JavaScript files: max 250 lines.** Same rule.
- **CSS files: max 400 lines.** CSS can get long naturally, but beyond 400 lines, split by concern (layout, components, themes).

**When to break these limits:** if a single, focused class (e.g., a repository with 15 related query methods) would be split awkwardly into smaller files that share no other responsibility, it's okay to go over — but comment why it's one file and not two. This should be rare.

When splitting:
- **One class per file.** Always. No exceptions.
- **Files are named after their single class** (PSR-4 autoloading).
- **Related files live in subdirectories.** `src/Pets/PetRepository.php`, `src/Pets/Pet.php`, `src/Pets/PetService.php` — not everything dumped in `src/`.

If you find yourself wanting to create a file called `Utils.php` or `Helpers.php` or `Common.php`, **stop and think harder**. Those names are red flags — they signal that you haven't figured out what the code actually is yet. Give things specific names that describe what they do.

**Build for extension, but do not over-engineer.** This codebase is a small core a beginner will later extend. So: content (pet species, areas, items) lives as data or clearly-commented config, never hardcoded; tunable values (cooldowns, XP thresholds, limits) live in one named, commented config location, never as magic numbers buried in code; each mechanic is built once cleanly as a copyable pattern with a documented "how to add another" recipe; and layer boundaries (controller / service / repository) are kept deliberately so pieces can change independently. But do NOT build plugin systems, event buses, generic frameworks, or speculative abstraction layers — that is over-engineering and it makes the code unreadable for the successor. The target: adding a new species or area, or changing how fast pets level, should be a data/config change following documentation — not a rewrite, and not navigating clever machinery. Extensible enough to build on; simple enough to read.

---

## 5. Functions and methods

- **Max 30 lines per function** (flexible if it's a single clear algorithm, like a loop over many items or a complex calculation). If longer, the function is probably doing too much. Extract.
- **Max 4 parameters per function.** If more are needed, pass an object or array with named keys.
- **One level of abstraction per function.** A function that does both "validate the pet name" and "update the database" is mixing levels. Split.
- **Function names describe what they do, not how.** `savePet()` not `insertIntoPetsTable()`. The latter leaks implementation detail.
- **Boolean parameters are a smell.** `createPet($data, true, false, true)` is unreadable. Use separate methods or a config object.

---

## 6. Database access

- **PDO only. Never `mysqli`. Never raw `mysql_*` functions.**
- **Prepared statements for every query with variable data. No exceptions.** String concatenation into SQL is a SQL injection waiting to happen.
- **Database access goes through a repository class.** Controllers never touch PDO directly. If a controller needs data, it asks a repository, which owns the SQL.
- **Every query has a comment explaining what it fetches and why.** Newcomers reading repository code should understand the intent without having to read the SQL.
- **Schema changes go through Phinx migrations.** Never edit the schema by hand on any environment.
- **Never `SELECT *`.** Always list the columns you actually need. This keeps contracts explicit and prevents accidental data leaks.
- **Think about indexes.** For every `WHERE` clause on a frequently-queried column (creature_id, owner_id, category), there should be an index. Indexes live in migrations, not somewhere to figure out later. Mention in a comment if a query would benefit from an index that doesn't exist yet.

---

## 7. Security defaults

These are baked into the project conventions. Breaking any of them is a bug, not a tradeoff.

- **Every form has a CSRF token.** The form-rendering helper adds this automatically; don't write forms that bypass it.
- **Every user-provided value is escaped on output.** Plates auto-escapes by default; do not use the "raw" variant without a comment explaining why it's safe.
- **Every user-provided value is validated on input.** Length limits, type checks, allowed-value lists. Validation lives in a dedicated validator class, not scattered in controllers.
- **Passwords are hashed with `password_hash()` using the default algorithm.** Never MD5, SHA1, or your own scheme.
- **Session cookies are `HttpOnly`, `Secure`, and `SameSite=Lax`.**
- **Rate limits apply to every state-changing public endpoint.** Login, registration, password reset, commenting, petting, renaming. Use the rate limiter service.
- **Secrets come from `.env`. Never hardcoded. Never committed.** If you need to reference a new secret, add it to `.env.example` with a placeholder value and document what it's for.

**Security is part of thinking, not a later phase.**

Before the rules, the reason. The accounts on this site belong to real people, and a meaningful share of them will be children. A security failure here is not an embarrassing bug — it is somebody's account taken, somebody's creatures gone, or a stranger reaching a child through a gap you left. The people running this site are two hobbyists with day jobs; they cannot watch it around the clock, so the code has to hold on its own overnight. **Care about this the way you would if the person on the other end were someone you knew.** A missing feature disappoints someone. A security hole hurts them.

Practically, that means: treat every request as hostile until proven otherwise, never trust anything the browser sends, rate-limit anything repeatable, and prefer the boring safe option over the clever one every single time. If you are ever unsure whether something is safe, it is not — stop and raise it.

Whenever an increment touches user data, authentication, permissions, money, uploads, or anything a player can type, the mandatory written plan must answer three questions *before any code is written*:

1. **Who is allowed to do this, and how is that enforced?** Name the check and where it lives. "The UI doesn't show the button" is not an answer — assume the request arrives directly.
2. **What is the worst thing a malicious user could do with this endpoint?** Consider: acting on someone else's data by changing an ID, submitting values outside the allowed range, replaying or racing the request to duplicate a reward, and pasting hostile content into any free-text field.
3. **What does the test suite prove about the above?** Every permission boundary and every limit gets an explicit test that it *refuses* the bad case — not only that it allows the good one.

If any of the three can't be answered, the increment isn't planned yet. Stop and think further rather than starting and patching afterwards.

**Threats specific to this site, to keep in mind by name:**
- **Alt-account farming.** Clicking another player's creature rewards both sides. Someone will make second accounts to click their own creatures. Design the reward so this is unattractive (per-actor limits, per-creature-per-day caps, no reward from an account that shares obvious signals with the owner) and say in the plan how you have limited it.
- **Currency and item duplication.** Any action that grants, spends, or moves something must be safe against the same request arriving twice at once. Use database transactions; never read-then-write across two steps without protection.
- **Uploads.** Admin file upload is the highest-value target on the site. Validate real file content, never trust the name or declared type, store under a generated name, and serve from somewhere nothing can execute.
- **The admin surface.** Anyone who reaches an admin route owns everything. Authorise on every request, log every action, and never add a route that runs arbitrary input.
- **Enumeration.** Search, profiles, and IDs must not let someone script their way to a list of all players.
- **Free-text fields.** Names and bios are the only place a player can put chosen words in front of another player. They carry the safety weight the removed messaging would have had.

---

## 8. Testing — part of the definition of done

A feature is not done until it has tests. "Works on my machine" is not done.

- **Every public method on a service or repository has at least one unit test.**
- **Every controller action has at least one integration test that hits a real test database.**
- **Edge cases get tested:** empty input, too-long input, missing required fields, permission-denied scenarios.
- **Tests are readable.** Test method names read like sentences: `testCommentIsRejectedWhenContainsBlockedWord`.
- **Tests use fixtures or factories, not hardcoded IDs from seed data.** Tests must be independent and runnable in any order.
- **If a bug is found, a test is written that would have caught it *before* the bug is fixed.** This prevents regressions.

**A passing test is not proof the feature works.** Automated tests call the code
directly, which means the test does the wiring — and it wires things the way the
person writing it *believed* they worked. A test that hands a controller input it
could never actually receive will pass forever while the feature is dead.

**And neither test suite can see the deployed database or a stylesheet.** Both run
against a *local* database that has been migrated, so neither can tell you the live
schema is behind the code. Both read HTML, so neither can tell you the CSS put the
whole site in a 250px column. Both of those shipped. When a change touches the
schema or the layout, the test suites are not the last line — the deployment guide
and your own eyes at 360px and 1280px are.

This is not hypothetical. The player search read its query from posted form
values while the form was a GET form; search never worked once, and its test was
green the whole time.

So: **before telling anyone a feature is ready, open it.** Run
`php bin/smoke-test.php`, which drives the real site over HTTP — it registers an
account through the real form, walks every page, submits the real forms and checks
the pages actually did their job, not merely that they rendered. Never hand over a
manual checklist for a page you have not loaded yourself.

If you're writing code and can't figure out how to test it, that is a signal the code is badly structured. Stop and restructure, don't skip the test.

---

## 9. UI rules — locked by project philosophy

These come from the Product Owner and are non-negotiable:

- **No dropdown menus. Anywhere. Ever.** Use tiles, toggle buttons, button groups, or radio-style card selections instead. If you think you need a dropdown, you need to rethink the interaction.
- **No JavaScript framework.** Vanilla JS only, with HTMX for partial updates where it genuinely helps. React, Vue, Svelte, etc. are not on this project.
- **Animations are CSS-first.** Only reach for JavaScript animation when the effect genuinely can't be done in CSS.
- **Mobile-first CSS.** Write the mobile layout first, then add desktop refinements with min-width media queries.
- **No custom color values in components.** Colors come from CSS custom properties defined in a central theme file. If you need a new color, add it to the theme, don't inline a hex value.
- **Animated creature images must display correctly.** Creatures are delivered as animated GIFs (and possibly animated PNG/WebP). They must loop automatically and render crisply. For pixel-art that is scaled up for display, apply `image-rendering: pixelated` so it stays sharp instead of blurring. Never bake a static frame where an animated source was provided.
- **Mobile is not optional and not an afterthought.** Every page, every component, every interaction must work and look good on a phone screen (~360px wide) from the moment it is built. Test layouts at mobile width first. Touch targets must be large enough to tap (min ~44px). If something only works on desktop, it is not done.
- **Aim for a sense of craft and a little magic.** This is a whimsical pet world, not a corporate dashboard. Within the constraints above, favour gentle, tasteful touches that make the site feel alive and cared-for: soft transitions between states, subtle hover/tap feedback, gentle ambient motion where it fits the art. Restraint matters — magic comes from a few well-placed details, not from animating everything. Performance and accessibility (respect `prefers-reduced-motion`) always win over spectacle.

### The confirmed look & feel

The aesthetic is **cosy autumn hygge — like coming home to a warm, lamplit room on a grey evening.** Warm, soft, a little magical, with small details that reward looking, but never loud or cluttered. The reference point is an enchanted keepsake / old-web personal page (think a gentle, grown-up take on an early-2000s profile page), executed with modern craft. A mockup expressing this exists; match its spirit, not a corporate template.

**Colour palette (the central theme file uses exactly these — no other hex values in components):**
- `--parchment: #F1E4C3` — warm cream, the main content surface
- `--panel: #E7D4A8` — slightly deeper parchment for cards/boxes
- `--purple: #7B3FA0` — primary accent (buttons, links, mid elements). *Amended 2026-08-14* from `#6A4C93` to rhyme with the magenta nebula clouds of the painted background; contrast on parchment is 5.43:1 (the old value was 5.42:1), so every AA pass holds.
- `--plum: #3D2B4F` — deep purple: body text on parchment, and dark chips/panels
- `--gold: #C9A227` — accent for borders, glows, highlights (the "sparkle")
- `--border: #B89A6A` — muted dividers and outlines
- Derived shades are allowed in the theme file if clearly named (e.g. `--night` for the sky behind the background image, a lighter gold for glows). Everything still lives in the theme file.

**Typography (load from Google Fonts; define as theme tokens):**
- **Fraunces** — display/headings (the characterful, slightly whimsical serif). Use its optical-size and soft settings for warmth.
- **Nunito** — body text (rounded, friendly, highly readable).
- **Space Mono** — small data, labels, and captions (the old-web "computery" texture). Never below ~0.7rem and never the only carrier of essential information.

**Background treatment** (*amended 2026-08-14*)**:** the page field is the artist's hand-painted starfield tile (`/assets/art/background.webp` — black space, magenta nebula, blue star-river, gold sparkles), tiled edge to edge the way old personal pet pages tiled their backgrounds, with parchment panels floating on it. `--night` is the fallback colour behind the image. This replaced the earlier plum-gradient treatment — still warm, enveloping, cosy-at-night, never a flat light page.

### The golden rules — what makes this site good to use

These sit above the detailed rules below. When a decision is unclear, decide by these.

1. **If it needs explaining, it isn't designed yet.** Someone should look at a screen and know what to do without instructions.
2. **One thumb, one hand, small screen.** Design the phone version first and make it genuinely pleasant, not merely possible.
3. **Never a dead end.** Every refusal names what to do instead and links there. "You need a rod — Sunny sells one" beats "You can't fish here."
4. **Say what happened.** Every action gets a visible, plain-language confirmation. Silence makes people click twice.
5. **Plain words, not clever ones.** "You need 2 more onions", not "Insufficient reagents". Write like a kind person, not a system.
6. **Nothing important lives in hover, colour, or motion alone.** Each of those excludes someone.
7. **Everything reachable by keyboard**, with focus always visible.
8. **Readable at 200% zoom** without sideways scrolling or overlapping text.
9. **Nothing is lost by accident.** Destructive actions confirm; wherever it's affordable, they're reversible.
10. **Never punish absence, never demand speed.** No mechanic penalises being away, and nothing requires fast reactions or precise clicking.
11. **When in doubt, gentler.** Between the demanding option and the kind one, this site always picks the kind one.
12. **Every milestone ends pixel-tidy, verified by eye.** Before an M is called done, the GUI is brought to a state fit to hand to an artist who notices single pixels: nothing floating, nothing cut off, nothing wrapping mid-word, nothing overlapping — checked by **looking at real renders** of every changed page at 360px and 1280px (`php bin/gui-shots.php` produces them), not by reasoning about the CSS. Tests cannot see a stylesheet; only eyes can. A milestone whose features all work but whose screens look unfinished is an unfinished milestone.

A screen that satisfies every technical rule below but fails these is not finished. And build the whole thing to load quickly and run smoothly — a site that's beautiful but slow is not finished either.

### Accessibility — required, not optional

The site must be usable by people with disabilities. These are hard requirements, part of the definition of done for any UI work:
- **Colour contrast meets WCAG AA.** Body text needs at least 4.5:1, large text at least 3:1. Concretely: deep plum text on parchment is fine; **gold text on parchment is NOT (too low contrast) — only use gold for text on the dark plum panels, or as non-text decoration (borders, glows, dots).** Check contrast whenever you place text on a colour.
- **Visible keyboard focus.** Every interactive element (links, buttons, inputs) has a clear `:focus-visible` style (e.g. a purple outline with offset). Never remove focus outlines without replacing them with something equally visible.
- **Touch targets ≥ 44px**, with enough spacing that they're not easily mis-tapped.
- **Semantic HTML.** Use real headings (`h1`, `h2`, …) in order, real `<button>`/`<a>`, `<nav>`, landmarks. Screen-reader users navigate by these.
- **Every creature image and meaningful image has descriptive `alt` text.** Purely decorative elements are hidden from screen readers (`aria-hidden`).
- **Never rely on colour alone** to convey meaning (pair it with text, shape, or an icon).
- **No essential information or action is hover-only** — it must be reachable by keyboard and visible without a mouse.
- **`prefers-reduced-motion` is fully respected** — ambient motion, glows, and transitions are disabled for users who ask for reduced motion; the site stays fully usable and pleasant without them.

---

## 10. When to ask, when to proceed

**Proceed without asking when:**
- The task is unambiguous and matches an existing pattern in the codebase
- You're fixing an obvious bug with an obvious fix
- You're adding tests for existing code
- You're writing documentation for existing code

**Stop and ask when:**
- A rule in this file would need to be broken to complete the task
- The task requires adding a new dependency to `composer.json` or the frontend
- The task requires a schema change that breaks backwards compatibility
- Two reasonable approaches exist and the choice matters long-term
- The sprint brief is ambiguous about a user-facing behaviour
- Something feels wrong but the instructions say to do it anyway

"Do not silently invent requirements that weren't in the brief." If the brief says "add a rename feature" but doesn't specify a rate limit, ask what the rate limit should be. Don't guess.

---

## 11. Naming conventions

- **Classes:** `PascalCase`. `PetRepository`, not `pet_repository`.
- **Methods and properties:** `camelCase`. `findById()`, not `find_by_id()`.
- **Database tables:** `snake_case`, plural. `pets`, `user_sessions`.
- **Database columns:** `snake_case`. `created_at`, `owner_user_id`.
- **Variables in PHP:** `camelCase`. `$ownerUserId`, not `$owner_user_id`.
- **Constants:** `SCREAMING_SNAKE_CASE`. `MAX_STICKERS_PER_PET`.
- **CSS classes:** `kebab-case`, BEM-style for components. `petpage__sticker-layer`.
- **Files:** match what they contain. A file with `class PetRepository` is named `PetRepository.php`.

No `$x`, `$tmp`, `$data`, `$result` unless the scope is genuinely two lines. Give variables names that describe what they hold.

---

## 12. Dependencies — add sparingly

Every Composer package or frontend library added to the project is a commitment. Ask before adding one.

Before adding a dependency, check:
1. Can this be done with vanilla PHP / vanilla JS in a reasonable amount of code?
2. Is the package actively maintained? (commits in the last 12 months, issues responded to)
3. Does the package have significantly more code than we need?
4. Does the package introduce its own conventions that conflict with ours?

When in doubt, write the small amount of code ourselves rather than pulling a package. Every removed dependency is one less thing that can break.

---

## 13. Git and commits

- **Small, focused commits.** One logical change per commit.
- **Commit messages follow the pattern: `<type>: <short summary>`** where type is one of `feat`, `fix`, `test`, `refactor`, `docs`, `chore`.
- **Never commit `.env`, secrets, or log files.** These are gitignored, but double-check before pushing.
- **Never commit broken code to `main`.** Work in feature branches, merge via PR after CI passes.
- **Never force-push to `main`.** Ever.

---

## 14. When you're about to finish a task, check

Before considering a task done, run through this checklist:

- [ ] All new functions and methods have educational comments
- [ ] No file exceeds its line limit (flexible if justified; see rule 4)
- [ ] Tests are written for new code and they pass locally
- [ ] `php bin/smoke-test.php` passes — the site was actually opened and used, not just unit-tested
- [ ] CSRF tokens are present on new forms
- [ ] User input is validated and output is escaped
- [ ] No secrets hardcoded; `.env.example` updated if new config was added
- [ ] No dropdown menus were added
- [ ] Documentation has been updated to reflect the change
- [ ] Commit message is clear and follows the pattern
- [ ] **If a migration was added, the handover SAYS SO, in the first paragraph.**
      Code deploys automatically; database structure does not. This checklist had
      no such line, and three migrations shipped without anybody being told —
      every page on the live site became `Unknown column 'energy'` from inside a
      repository. Passing tests and a passing smoke test both said fine, because
      the local database had been migrated. Saying "run `phinx migrate -e
      production`" is part of finishing the work, not an optional footnote.
- [ ] **Performance:** Database queries are indexed where appropriate; N+1 problems avoided; hot paths are efficient
- [ ] **Architecture:** The code structure makes sense and doesn't hide performance issues or future scaling problems
- [ ] **The screens were LOOKED AT** (golden rule 12): every page this change touched
      was rendered at 360px and 1280px (`php bin/gui-shots.php`) and inspected by
      eye. Neither test suite reads a stylesheet — a layout collapsed into a strip
      and a count glued to a wrapped name both shipped with everything green.

If any are missing, the task is not done. Go back and finish it before moving on.

---

## 15. When rules conflict with what you're being asked

If a human in the loop asks you to do something that contradicts this file — for example, "just quickly add it as a dropdown for now, we'll fix it later" — **stop and flag the conflict**. Do not silently comply. The rules in this file were put here on purpose, and "we'll fix it later" is how codebases decay.

Explain the conflict, offer a conforming alternative, and let the human make an informed choice. If they still want to break the rule, they need to amend this file first — that creates an audit trail and forces a real decision rather than an accidental drift.

---

*This file is the coding contract for Felkyo v2. If a rule here is wrong or needs updating, amend this file deliberately — do not work around it.*
