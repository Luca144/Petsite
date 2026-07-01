# Felkyo Creatures — Build Execution Plan for Claude Code

**Read this fully before doing anything. Then read `CLAUDE.md`. Then begin.**

This document tells you *what* to build, *in what order*, and *how to operate* while building it. `CLAUDE.md` tells you *how to write the code itself* (style, file limits, security, no dropdowns, etc.). Both apply at all times. If they ever conflict, stop and ask.

---

## 1. What this project is, and who inherits it

This is **Felkyo Creatures**, a lean, clean creature-collecting website. The animals players collect are called **creatures** (never "pets") in all user-facing text and in the database naming; the one exception is the interaction verb, where petting a creature keeps the verb "pet"/"petted". It is being built as a learning project and as a foundation that **a person with very little coding experience will take over later**. That successor is the most important reader of everything you write.

**This single fact governs every decision:** when you choose between clever and obvious, choose obvious. When you choose between concise and clear, choose clear. When you wonder whether a comment is needed, write it. The successor cannot read your mind, cannot ask you questions later, and will be learning to code *from this codebase*. Treat the code as teaching material that happens to also run.

If at any point you produce something a motivated beginner could not understand by reading it plus its comments, you have failed the prime directive, regardless of how well it works.

---

## 2. Build it to be extended and customised — without over-engineering

This project is, in effect, a small clean core that the successor will later extend and customise (more pet species, more areas, more mechanics, different look and feel). So extensibility matters. But the successor is a beginner, so it must stay readable. **These two goals are not in tension — the patterns below make the code both easier to extend AND easier to read.** Follow them. Do NOT go beyond them into abstract engine machinery (plugin systems, event buses, generic frameworks, speculative abstraction layers). That is over-engineering: it adds complexity for flexibility that will likely never be used, and it makes the code unreadable for the successor. Build for the extensions that are clearly coming; do not build for hypothetical ones.

The four patterns to follow throughout:

**(1) Content lives as data, not as hardcoded logic.** Pet species, exploration areas, item definitions, and similar "content" are stored as data (database rows or a clearly-commented config file), never baked into code. Adding a new pet species or a new area should mean adding a row or a config entry — not writing or copying logic. This is simpler to read than hardcoded content, not harder.

**(2) Tunable values live in named config, not as magic numbers.** Cooldown lengths, XP thresholds, daily adoption limits, exploration click limits, and any other "knob" live in a single well-commented config location with names that explain what they do. Changing the feel of the game (e.g. faster levelling) should mean editing one labelled value, not hunting through code for a bare `3600`.

**(3) Each mechanic is built once, cleanly, as a copyable pattern — and the copy recipe is documented.** When you build the one exploration area, build it so a second area can be added by following a clear, written recipe (in the developer guide), not by reverse-engineering the code. The code demonstrates the pattern once; the docs say "to add another, do this." This is the honest, beginner-friendly form of extensibility: a repeatable recipe, not a framework.

**(4) Layer boundaries are a deliberate goal, not just a style.** Controllers never touch the database directly; data access lives in repositories; business rules live in services. State this reason in comments where it matters: it means a piece can be changed or replaced without breaking the others. The successor needs to understand *why* the boundary exists so they don't casually cross it.

The test for all four: could the successor add a new pet species, add a new exploration area, or change how fast pets level up — by following documentation and changing data/config, without rewriting core logic? If yes, you've hit the right level of extensibility. If achieving that required machinery a beginner can't follow, you've gone too far — pull back.

---

## 3. How you operate (the working agreement)


You work **mostly autonomously**, in strict increment order, stopping only for the two reasons below. Do not wait for permission to proceed between increments unless a stop-condition is hit.

### You STOP and ask the human when:

**(a) A major decision arises.** A major decision is any of:
- A database schema change that affects future increments
- Genuine ambiguity in this plan or in a feature's intended behaviour
- A choice between two approaches that has long-term consequences (not interchangeable internals)
- Anything that would require breaking a `CLAUDE.md` rule
- Adding a new dependency (Composer package or frontend library)
- Anything where you find yourself guessing at what the human actually wants

When you stop for a major decision: state the decision clearly, give 2–3 options with honest trade-offs, give your recommendation, and wait. Do not proceed on a guess.

**(b) A point is reached where the human's eyes are genuinely required.** Primarily: the schema sign-off (0.2), which is mandatory. Beyond that, surface a checkpoint for human testing when it actually matters — when an increment has produced user-facing behaviour the human should verify, or when something needs a real human judgement. Do NOT stop mechanically after every single increment regardless of significance: batch small, low-risk increments and present them together; pause for testing where it counts. Use judgement — frequent enough that nothing important goes unverified, rare enough that the human isn't interrupted for trivia.

When you do present a checkpoint, produce a **manual test checklist** (see section 5) for the human to run.

### You DO NOT stop for:
- Internal implementation details (variable names, private method structure)
- Obvious bug fixes with obvious causes
- Writing and running your own automated tests
- Anything this plan already specifies clearly
- A checkpoint after a small, low-risk increment that can be sensibly batched with the next one

The goal: the human is involved when it's important — the schema, genuine forks, and user-facing behaviour worth verifying — not on a mechanical per-increment basis. Respect their time by deciding what you can decide and batching what's minor; protect the project by escalating what genuinely needs them.

---

## 4. The per-increment workflow (do this for EVERY increment)

This is the loop. It is not optional and the order matters. The first step is the forced think-before-code gate.

**Step 1 — PLAN (before writing any code). This gate is mandatory and non-negotiable.**
Think the change through *before* writing it. Produce a short written plan for the increment containing:
- *Goal:* one sentence — what this increment delivers
- *Approach:* how you intend to build it, which files/classes are involved, and the *simplest* design that satisfies the goal
- *Data:* any schema touched, any new fields, any queries
- *Edge cases:* what could go wrong, what unusual inputs or states exist, what you will guard against
- *Tests:* the specific automated tests you will write, and what each verifies
- *Open questions:* anything you're unsure about

In the plan, deliberately choose the **leanest correct design**. Before committing to it, ask yourself: is there a simpler way that still meets the goal and the `CLAUDE.md` rules? If yes, take it. The plan is where bloat gets caught — a sprawling plan produces sprawling code. Aim for targeted, minimal, purposeful work, not volume. More code is not more progress; the right small amount of clean code is.

If the plan surfaces a major decision (section 3a), stop and ask now — *before* coding, not after. Cheap to change a plan; expensive to change built code.

**Step 2 — WRITE TESTS FIRST where practical.**
For logic with clear inputs and outputs (auth rules, cooldowns, XP thresholds, loot weighting), write the tests before the implementation. This forces you to define correct behaviour before building it, and it catches you when the implementation drifts.

**Step 3 — BUILD.**
Implement against your plan, making **targeted, minimal changes** — touch only what the increment needs, add only the code the goal requires. Follow `CLAUDE.md` to the letter — educational comments, file size limits, prepared statements, one class per file, no dropdowns. Build the smallest thing that satisfies the increment's definition of done. Do not build ahead into future increments, do not add speculative flexibility, do not generate sprawling files where a small focused one does the job. If a file is growing large, that is a signal to stop and reconsider the design, not to keep adding. Clean and purposeful beats big.

**Step 4 — TEST (automated).**
Run the full test suite, not just the new tests. Everything must pass. If something breaks, fix it before proceeding. A red suite is a hard stop — never build on top of failing tests.

**Step 5 — SELF-REVIEW.**
Re-read your own diff as if you were the non-coder successor. Ask: would they understand this? Is anything over their head without a comment? Did any file exceed its limit? Is there a clever bit that should be made obvious? Fix what fails this review.

**Step 6 — CHECKPOINT.**
Produce, for the human:
- A one-paragraph summary of what was built
- The manual test checklist (section 5)
- Anything you want them to look at or be aware of
Then wait for their confirmation before the next increment.

---

## 5. Testing discipline

Three kinds of testing, with clear ownership.

**Unit tests — you write and run, they gate every increment.**
Every public method on a service or repository gets at least one. Edge cases get tested explicitly: empty input, over-length input, missing fields, permission-denied, boundary values (e.g. XP exactly at a threshold). Test names read like sentences (`testPettingIsRejectedDuringCooldown`).

**Integration tests — you write and run, against a real test database.**
Every route/controller action gets at least one that exercises the real path through router → controller → repository → database. Tests run against a separate test database and roll back between tests so they're independent and order-free.

**Manual test checklist — you write it, the HUMAN runs it.**
This is the "come back to me for tests" the human asked for. At each checkpoint, produce a numbered, plain-language checklist a non-technical person can follow, covering the user-facing behaviour of the increment. Example for an auth increment:
1. Go to /register, create an account with valid details — should succeed
2. Try to register the same email again — should be refused with a clear message
3. Log out, then log in with the right password — should succeed
4. Log in with the wrong password — should be refused
5. While logged in, reload the page — should still be logged in

Write these for behaviour, not internals. The human runs them, reports pass/fail, and you fix any failures before the next increment.

**The rule:** an increment is not "done" until its automated tests are green AND the human has confirmed the manual checklist. "The code works" is not done. "Done" is "proven to work, by both machine and human."

---

## 6. The build sequence

Strict order. Each increment is fully complete (planned, built, tested, self-reviewed, human-confirmed) before the next begins. Each increment leaves the project in a working state. This is waterfall in sequence, incremental in delivery.

### PHASE 0 — Foundation

**0.1 — Project skeleton.**
Composer initialised; directory structure (`public/`, `src/`, `templates/`, `tests/`, `migrations/`, `config/`, `docs/`); router; templating engine; database connection via config (no hardcoded credentials); logging; PHPUnit configured with a separate test database and one passing dummy test; `CLAUDE.md` committed at root. Definition of done: a single "hello" route renders through the full stack (router → template) and the dummy test passes.

**0.2 — Schema design. [MAJOR DECISION — stop and get approval]**
Propose the full database schema for the *whole* project before building any feature tables. Design for what's coming even if not built yet: users, pets (with fields for growth/XP/stage and an owner foreign key), and the shape of tables for daily-adoption tracking and exploration. You do not build all the logic now, but the schema should not need painful retrofitting later. Present the proposed schema with reasoning, get human approval, then set up migrations. See section 8 for a recommended starting shape.

**0.3 — Design language and base layout.**
Establish the visual foundation the whole site inherits, so "a magical, crafted feel" and "works on mobile" are built in from the start rather than bolted on. Using the artist's branding (logo, colour palette, typography — delivered for this increment), create: a central theme file of CSS custom properties (colours, fonts, spacing, border-radius, shadow/glow tokens); a base page layout (header, footer, content area) that is mobile-first and looks good at ~360px wide before any desktop refinement; the web-font loading; and a small set of reusable styled components (buttons, cards/tiles, form inputs — no dropdowns) that every later page reuses. Respect `prefers-reduced-motion` from the start. Definition of done: the "hello" route now renders inside the real themed layout, looks intentional and on-brand on both a phone and a desktop, and later increments have a consistent visual kit to build with. Manual checklist: open the page on a phone and a desktop, confirm it looks deliberate and on-brand in both and that text is readable and nothing overflows.

### PHASE A — Core ("it works and it's mine")

**A.1 — Accounts.**
Register, log in, log out, sessions, password hashing (`password_hash`), input validation, CSRF on forms. No email verification (deliberately out — see scope). Build registration so it can later be disabled by a config flag (it will be closed in the deployed demo — see Phase D); you don't need the flag now, just don't build registration in a way that makes adding it awkward. Automated tests for all auth logic and permission boundaries. Manual checklist covers register/login/logout/wrong-password/session-persists.

**A.2 — Creature ownership.**
A pet exists in the database, owned by a user (the one-to-many relationship, even though Core has one pet — the schema and queries are built for many). A new user receives a starter pet, or a minimal route creates one. Tests for pet creation and ownership integrity. Manual checklist confirms a logged-in user has a pet recorded.

**A.3 — Creature page.**
A page displaying one pet: image, name, species, owner, created date, and current state fields. The pet image is an **animated GIF that loops automatically**; if it is pixel-art scaled up for display, apply `image-rendering: pixelated` so it stays crisp (per `CLAUDE.md`). The page must look good and the pet must be clearly visible on a phone. Correct handling of "does this pet exist" and "who can see it." Tests for rendering and access rules. Manual checklist: visit a pet's page on phone and desktop, confirm the animated pet displays, loops, and is sharp not blurry.

**CORE CHECKPOINT.** A person can register, log in, has a pet, sees its page, and everything persists across logout. Confirm the whole Core flow with the human before starting Phase B.

### PHASE B — The Game ("worth using")

**B.1 — Interaction loop.**
One action (pet/feed) changes one stat, enforced by a cooldown. Tests for the stat change, cooldown enforcement, and rejection of action during cooldown. Manual: perform the action, see the stat change, confirm it's blocked until the cooldown passes.

**B.2 — Growth.**
The interaction grants XP; XP thresholds produce levels; level thresholds produce stage transitions (baby → juvenile → adult), which swap the displayed image. Each stage has its own animated image; swapping stages swaps which animated GIF is shown. Tests for threshold logic, transitions, and the exact-boundary case. Manual: interact repeatedly, watch level and stage change, confirm the animated sprite swaps cleanly at each stage.

**B.3 — Collection.**
Many pets per user (relax the Core constraint), and a collection view listing the user's pets. Tests for the one-to-many query and the view. Manual: own several pets, see them laid out.

**B.4 — Daily adoption.**
Once per day, adopt a new pet from a small pool. Tests for the daily cooldown, pet creation via adoption, and pool selection. Manual: adopt a pet, confirm a second same-day attempt is refused.

**B.5 — Exploration area.**
One themed area: a background with clickable spots that yield a weighted-random reward (chance at a new pet), with a per-visit limit that refreshes. Tests for weighted selection, limit enforcement, and reward granting. Manual: explore, click spots, receive rewards, hit the limit. (This is the reusable template for all of Skerro's future explore areas — build it cleanly and generically enough to copy.)

**B.6 — Seeing other people's pets.**
Public pet pages and a browse/recent list. Tests for visibility rules (logged-out can view public pets) and the browse query. Manual: view another user's pet, browse the list.

**B.7 — Currency.**
A single in-game currency, earned when the user's pet is petted by others (it ties into the existing interaction loop). Stored as a balance on the user. Earning is anti-spam capped (the petting cooldown already limits it). Display the balance somewhere sensible. This is the *foundation only* — one currency, one earning source. No trading, no marketplace. Tests for earning, balance updates, and that the cap holds. Manual: get petted, watch balance rise; confirm it can't be farmed past the cap.

**B.8 — Inventory.**
A page showing what the user owns, grouped by type. Built generically enough that future item types slot in by data, not new code (per the extensibility principles in section 2). For now it holds whatever the shop sells (see B.9). Tests for the ownership query and display. Manual: own some items, see them listed.

**B.9 — One shop.**
A single shop that sells things for currency. Purchases deduct currency and place the item in the user's inventory (or apply directly, depending on the item type chosen). One shop only — no multiple themed shops, no player shops, no trading. Build the purchase flow generically (a shop has items, an item has a price, buying validates balance → deducts → grants) so adding more shops or items later is a data change, not new logic. Tests for: successful purchase, purchase blocked when balance too low, balance and inventory updated correctly, no negative balances possible. Manual: earn currency, buy something, see balance drop and item appear; try to buy something too expensive and confirm it's refused.

*Scope guard for B.7–B.9:* this is the economy **foundation**, deliberately minimal. The full economy (trading, player shops, multiple themed shops, complex item types and effects, crafting) stays out — those are extensions the successor builds on this foundation. Build the foundation clean and data-driven so they can.

**GAME CHECKPOINT.** The full loop works: collect via daily adoption and exploration, grow via interaction, earn currency by being petted, spend it in the shop, see what you own, and see others' pets. Confirm with the human before Phase C.

### PHASE C — Frosting ("sparkle")

**C.1 — Pet bio.**
Owner can write a bio on their pet; others can't edit it. Content length-limited and filtered. Tests for ownership-gated editing and length limits. Manual: edit own pet's bio, confirm you can't edit another's.

**C.2 — Character and polish.**
Species flavour text, friendly empty states, a themed 404 page, general tidy. Light tests where logic exists; mostly a polish pass.

**C.3 — The magic pass.**
This is where the site goes from "works" to "feels special." A deliberate, restrained pass adding the touches that make Felkyo feel like a living, crafted world rather than a functional form. Candidates (pick the ones that earn their place — do not add all of them, and do not over-animate): gentle transitions between page states; subtle hover/tap feedback on interactive elements; a small satisfying animation when you pet a pet or when it levels up / changes stage; tasteful ambient motion where the art supports it; the artist's decorative flourishes (frames, dividers, background textures) applied consistently. Hard constraints: every effect must respect `prefers-reduced-motion`, must not hurt mobile performance, and must not get in the way of using the site. The test of success is emotional, so the manual checklist is: use the site on phone and desktop and confirm it feels warm, alive, and intentional — and that nothing is annoying, slow, or in the way. Restraint is the skill here: a few well-placed details, not sparkles on everything.

**C.4 — (Optional) Guestbook. [Discuss before building]**
The riskiest Frosting item due to spam/abuse surface. If the human wants it, treat spam protection (rate limits, content filter, new-account silence) as part of its definition of done. Flag and discuss scope before starting.

**FROSTING CHECKPOINT + STABILISATION.** Everything works together, full test suite green, documentation complete and readable by a beginner. The application itself is feature-complete. Confirm with the human before Phase D.

### PHASE D — Deployment (the production learning exercise)

The goal of this phase is to get Felkyo onto a real, live URL — as a learning exercise in deploying to production, NOT to open a real user-facing service. It stays a **closed demo**: seeded with demo data, registration closed, no real users, no real personal data. The human is learning the deploy pipeline; they are deliberately not taking on the obligations of running a live service.

**Security note — important.** The baseline security rules in `CLAUDE.md` (prepared statements, password hashing, CSRF, input validation) apply throughout and are not optional. For a closed demo with registration disabled and no real user data, that baseline is sufficient — the attack surface is minimal and there is nothing sensitive to protect. **But this is explicitly not enough for a real public service.** The moment anyone considers opening registration to real users, a proper security pass is required first (review of auth, sessions, access control, rate limiting, data-protection/GDPR obligations, secrets handling, dependency vulnerabilities). Write this clearly into the deployment guide so the successor knows the closed-demo security posture must not be mistaken for production-ready security.

**D.1 — Production preparation.**
- Separate all configuration from code: production settings come from environment variables, never hardcoded. Confirm no secrets are committed.
- Add a **closed-registration mechanism**: a config flag that disables public registration (so the deployed demo runs only on seeded accounts). Build it as a simple flag, on by default in production.
- Add a **seed script** that populates the demo with a handful of demo accounts and pets, so the live site looks alive without any real user data.
- Add a clear **"development demo" banner** to the site so anyone visiting understands it is not a live service.
- Tests: confirm the registration flag actually blocks registration when off; confirm the seed script runs cleanly.
- Manual checklist: with the flag off, registration is unavailable; seeded demo data displays correctly.

**D.2 — Deploy to a managed platform. [MAJOR DECISION — confirm platform]**
- Target a managed platform (Railway, Render, or Fly.io — the human has chosen the managed-platform route over a self-managed VPS). Recommend one with reasoning and confirm before proceeding.
- Connect the Git repository to the platform's deploy pipeline (deploy-on-push).
- Provision a managed production database on the platform.
- Set production environment variables (database credentials, app config) in the platform, not in code.
- Run migrations against the production database.
- Run the seed script against production to populate demo data.
- Confirm the site is live at its URL with working SSL (the platform handles SSL automatically — confirm it is active).
- **Check and document the platform's database backup situation.** Find out whether the managed database is backed up automatically (most platforms do, some only on paid tiers), how often, and how a restore would work. Write the answer into the deployment guide. For a closed demo with no real user data this is low-stakes, but the human should *know* the backup status rather than assume it.
- Manual checklist: visit the live URL, confirm the demo works end-to-end (log in as a seeded account, view pets, interact, explore), confirm registration is closed, confirm SSL padlock is present.

**D.3 — Verify and document the deployment.**
- Confirm the full loop works on production exactly as it does locally.
- Write a plain-language **deployment guide** in `docs/`: how this got deployed, step by step, and how to deploy an update (push to the repo → platform redeploys). Written so the non-coder successor — and future-you — can repeat it without re-learning from scratch.
- Document how to run the seed script and toggle the registration flag, in case the successor ever wants to open registration (with a clear note that doing so means taking on real-user and data-protection responsibilities).
- Manual checklist: following the deployment guide, the human can identify how they would push a change and have it go live.

**DEPLOYMENT CHECKPOINT + FINAL HANDOFF.** Felkyo is live as a closed demo at a real URL, the human understands the deploy pipeline, all documentation (developer guide, setup guide, schema doc, deployment guide) is complete and beginner-readable, and the full test suite is green. **This is the defined finish line and the handoff point.**

---

## 7. Documentation you produce as you go

Not at the end — alongside each increment, because the successor needs it and end-loaded docs never get written.

- **Inline:** educational comments per `CLAUDE.md`.
- **Per increment:** update a running developer guide in `docs/` explaining what the increment added and how it works, written for a beginner.
- **Setup guide:** keep a "how to run this from scratch" doc current (clone, install, configure, migrate, run) so the successor can get the project running on their own machine.
- **Schema doc:** keep a plain-language description of the database — what each table and column is for and why — updated whenever the schema changes.
- **Deployment guide:** produced in Phase D — how the site was deployed and how to deploy an update.

A feature whose documentation a beginner couldn't follow is not done.

---

## 8. Recommended starting schema (for 0.2)

This is a starting point for the schema proposal, not a final spec — refine it, justify changes, and get human approval before building.

- **users:** id, username, email, password_hash, currency_balance, created_at, last_login_at
- **creatures:** id, owner_id (FK → users), species, name, stage (baby/juvenile/adult), xp, level, happiness (or chosen interaction stat), last_interacted_at, created_at
- **adoptions:** user_id (FK), last_adopted_at — to enforce the daily limit (or fold into users)
- **exploration_visits:** user_id (FK), area, clicks_used, window_started_at — to enforce per-visit limits
- **items:** id, key/slug, name, description, price, type — the definitions of what exists to be sold/owned (data-driven, so new items are rows not code)
- **inventory:** id, user_id (FK), item_id (FK), quantity — what each user owns
- **shop_items:** which items a shop offers (for one shop now, but structured so more shops are possible later)

Design notes to weigh in the proposal: keep current-stage derivable from xp/level so state is consistent; decide whether interaction stat decays over time (computed from last_interacted_at) or is a simple counter (the human chose the simpler counter+cooldown model — confirm); keep species as data (a species table or a config list) rather than hardcoded, since more species are expected later; keep items and shop offerings as data too, for the same reason; add database indexes on the columns that get looked up often (foreign keys like owner_id, and anything queried for lists or lookups) so queries stay fast as data grows — a small, standard piece of clean schema design, not premature optimisation. The economy tables above are the *foundation* — design them clean and data-driven, but do not build trading, player shops, or complex item mechanics (those are later extensions).

---

## 9. First action

Do not start coding. Your first action is to read `CLAUDE.md`, then produce the **plan for increment 0.1** per the section 4 workflow, and the **schema proposal for 0.2** per section 8, and present both to the human for the schema sign-off before building. Begin.
