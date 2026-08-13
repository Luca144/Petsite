# Felkyo Creatures — Build Plan 2 (Milestones M1–M13)

**Read this together with `felkyo-build-plan.md` and `CLAUDE.md`.**

The first build plan took the site from nothing to a live, feature-complete closed demo. This plan covers what comes next.

**The operating rules do not change.** The working agreement, the mandatory think-before-code gate, the testing discipline, the extensibility principles, the security questions and the golden rules all stay exactly as defined in `felkyo-build-plan.md` and `CLAUDE.md`. They are already in force. This document says **what** to build and **in what order**.

The milestone numbers here match the ones the artist sees, so everyone is talking about the same thing. The artist's design document is the content source from M9 onward.

---

## 1. The insight this plan is built on

The design document is long. Read casually it looks like fifty features. It is **four engines wearing twelve costumes**:

- **Foraging, fishing, mining and hunting** are the same activity: pick an area, check the tool, roll a weighted table, decrement a limit. The existing exploration area is already this engine — generalise it, don't rebuild it.
- **Cooking and potions** are the same crafting engine.
- **All eighteen shops** are the same shop with different data.
- **Farming** is the one genuinely new mechanic; **care** extends the petting loop that already exists.

Build each engine once, then content is data. That is the difference between a project that grows and one that stalls.

## 2. The split that shapes this plan

The site is already live as a closed demo, so the gate is not "launch" — it is **opening registration to real people**.

**Part One (M1–M7)** is everything that must exist before strangers can sign up, ordered by what a pet site needs to feel complete and to be safe.
**Part Two (M8–M13)** grows the world while it is live.

Every milestone is a legitimate stopping point.

## 3. Principles specific to this stage

**(a) Engines before content.** Never hand-build the fifth loot table. If you are writing similar code a second time, stop and generalise.

**(b) Content must be addable without a developer.** The test for every engine: could the artist add an area, item, recipe, shop or theme through the panel, following a written recipe in `docs/`? If not, the engine isn't finished.

**(b2) Every content type ships with its panel screen, in the same milestone.** From M2 onward, a new kind of content is not finished until the panel can create and edit it. Treat it like tests and documentation — part of done, never a follow-up.

**(c) Art never blocks code, code never blocks art.** Build against placeholders; real art drops in as a file swap.

**(d) Keep the loop kind.** Plants gain a click a day on their own, fish are caught unharmed, creatures can be frozen at a stage their owner loves. No mechanic punishes absence, no reward requires grinding. Where a choice could go demanding or gentle, go gentle.

**(e) Safety by design: no privacy, no free text.** Players cannot message each other, cannot type into each other's guestbooks, and cannot write on cards or bottles. Every player-to-player communication is **fixed, pre-authored options**, and nothing sent between players is private. The remaining free-text surfaces — account names, creature names, bios — therefore carry the safety weight messaging would have had, and are handled in M1.4. Any new field a player can type into and another can see belongs to the same category and must be raised, not quietly added.

**(f) The artist is a user of this system too.** The panel is not an afterthought bolted onto the site — it is the tool one person will use several times a week for years. It gets the same care for usability and accessibility as the player-facing pages, and the same golden rules apply to it.

---

# PART ONE — Before opening registration

## M1 — Identity, items, and the words people choose

**M1.1 — The owned-thing model. [MAJOR DECISION — stop and get approval]**
Four kinds of owned thing with different attributes: **items** (name, type, value, picture; sell, discard, recycle), **creatures** (player-named, species, owner, birthday, click count, mood; rehome, release, freeze aging), **plants** (player-named, type, growing time, clicks; sell, discard) and **fish** (player-named, breed, catch date, size; sell, release, and later displayed in a tank).
Propose the model — one polymorphic table, separate tables, or a shared table plus type-specific detail — with honest trade-offs and a recommendation. Get sign-off before building. This is the schema decision of the whole plan. Note that fish will later be arranged in a tank (M10), so a fish needs a stable identity, not just a stack count.
Tests for the chosen model; manual checklist confirms existing creatures and inventory still work.

**M1.2 — Item categories and the coloured item card.**
Category-keyed background colours so a player knows instantly what a thing is and what they can do with it. Categories are data. One reusable item-card partial: name, art, category colour, available actions.
**Colour is never the only signal** — pair each category with an icon and a text label, and check contrast. A colour-blind player must lose nothing.
Add the item detail view and sell/discard. Tests for rendering, action permissions, correct sale credit.

**M1.3 — Profiles, avatars, and making a page your own.**
A profile page per player: their creatures, a short about text, a place to visit.
**Avatars come from a set the project provides; players never upload their own.** User-uploaded images would mean image moderation, which is far harder than text moderation and the easiest route for something genuinely harmful onto the site. A chosen set removes that entirely and keeps the site visually coherent.
**Personalisation is real but comes from a menu** — avatar, colour scheme or background from a set, which creatures are featured and in what order, decorative frames and trinkets the player owns, plus the about text. Same approach for creature pages: chosen decoration, plus the bio. Nothing typed except the text fields, nothing uploaded.
Show the player clearly what others can see. Tests for visibility rules, owner-only editing, and that only project-provided or owned options can be selected.

**M1.4 — Player-chosen text: names and bios. [Highest-priority safety increment in Part One]**
With messaging removed, these are the only free text a player can put in front of another. Build as one increment:
- **Validation and limits.** Short length caps, allowed character sets, no repeated or edge whitespace, no empty-looking names. Reject **zero-width and bidirectional-override characters** outright — they exist almost only to hide or scramble text.
- **No links or contact details in any free-text field.** Reject URLs, bare domains, `@handles`, and named platforms, plus the obvious evasions (spaced or dotted domains, "d0t", digit-for-letter). Document honestly that this is best-effort: it stops the casual case and makes the deliberate case obviously deliberate, which is what makes a report easy to act on. **This is the most important filter on the site** — everything else is contained by design, and a link is the one thing that leads somewhere none of these protections reach.
- **A blocklist filter** on names and bios, applied on save, held as data the humans can edit. Match case-insensitively and against simple obfuscations. Be honest in the docs that a blocklist catches the obvious and nothing more.
- **Impersonation protection.** Reserve names containing admin, mod, moderator, staff, official, felkyo, and any name rendering as an existing name via lookalike characters.
- **A report button** on every player-visible name, bio and profile. One tap, a **short fixed list of reasons** (no free text), which files a report.
- **Report categories drive priority.** A report about a rude name and a report about someone trying to reach a child must not sit in the same undifferentiated queue. The category is chosen by the reporter from the fixed list and determines where the report surfaces.
- **Reported bios and cards auto-hide pending review**, showing a neutral "under review" placeholder rather than vanishing. Names do not auto-hide — that would break pages; they are flagged instead. Rationale: with two part-time humans, a report may wait hours. Auto-hiding makes the worst case a briefly hidden innocent bio rather than days of harmful text in public.
- **Report acknowledgement** to the reporter: a simple "we've received this and will look into it". Silence teaches people not to report, and reports are the main safety mechanism here.
- **Bios are the highest-risk of the three**, being long enough to hold contact details or an approach. Raise whether new accounts should have bios hidden until a small age or activity threshold. Do not decide alone.

Tests: every validation rule refuses its bad case explicitly; filter and obfuscation matching; reserved names refused; link detection; report creation with category; auto-hide behaviour; and confirmation that a name containing SQL syntax or HTML is stored and rendered as inert text.

**M1.5 — Finding people: search and public profiles.**
Search by name and open a profile. Safe under this design: with no private channel, no free text and publicly displayed cards, the worst someone can do with a found profile is send a pre-written card everyone can see.
Conditions, each nearly free: **prefix matching, not a browsable index**; **no "newest members" listing and no sorting by join date** (new accounts are the least familiar with the site and most likely to be young — a chronological list of arrivals is precisely the tool for finding them); **nothing exposed that isn't already public on the profile**; **a one-setting opt-out**; **rate-limited** so the playerbase can't be enumerated; report control on every profile.
Tests: prefix matching, unfindable players excluded from every result path, no endpoint returning players by recency or in bulk, rate limiting, no non-public field in any response.

**M1 CHECKPOINT.** Players have identities and can find each other; everything they can type is bounded, reportable and triaged.

---

## M2 — The creator's panel

**This is the milestone that decides whether the project needs a developer forever.** Everything is built content-as-data so it can grow without code, but data currently means database seeds, which still means a developer. The panel closes that gap.

It comes early, before most of the content it manages exists, so that everything after ships with its management screen rather than accumulating a backlog only a developer can change.

**M2.1 — Admin foundation and roles.**
Four roles, each seeing only what it needs:
- **Owner** — everything, including security settings and role assignment.
- **Moderator** — the report queue, account history, notices, and moderation actions. No content editing, no settings.
- **Artist** — content: creatures, items, shops, cards, themes, library. No moderation, no settings, no security.
- **Coder** — technical settings and maintenance actions.
Roles are additive and assignable only by the owner. This exists so someone can be given help without being handed the whole site.
Required: every admin action written to an audit log (who, what, when, before, after); admin routes authorised **on every request**, not only at entry; rate limits; admin sessions treated as more sensitive than player sessions. Raise whether admin accounts should require a second factor before opening — the recommendation is yes.
Tests: each role refused on every route outside its scope, audit log records each action, privilege cannot be escalated through any normal endpoint.

**M2.2 — Image upload and where files live. [MAJOR DECISION — stop and get approval]**
The panel accepts sprites, item icons, NPC portraits, card art, area backgrounds, library art.
**Check where uploaded files can actually persist before building anything.** Container platforms commonly have an ephemeral filesystem, meaning uploads vanish on the next deploy. Verify how the current hosting behaves and present the options — persistent volume, object storage, or keeping art in the repository — with honest trade-offs. Say plainly that the repository option is cheapest but keeps a developer in the loop, which is the exact problem this milestone exists to solve.
Upload safety is not optional: validate real file content rather than extension or declared type, cap dimensions and size, generate the stored filename, serve from a path that cannot execute, strip metadata.
Tests: a disguised non-image refused, oversized file refused, hostile filename cannot escape its directory, uploaded file served inert.

**M2.3 — One page per creature.**
The single most important screen in the panel. Everything about one creature species on one screen: all three stage sprites, alternate forms, per-stage blurbs, stats and flags, library art and lore, adoptable and starter settings. Upload it all in one session, save as you go, publish when ready.
**Why this shape:** the artist's previous project died partly under the weight of making art, then uploading it, then setting values, then uploading again to a separate wiki. One screen per creature removes that entirely.
Include: **preview at any stage**, rendered on the real page layout, phone and desktop, before publishing; **one-click art replacement** that updates every reference and defeats caching; **draft and publish states** so a half-finished species is never live; **undo / revert to previous version**.
Tests: create, edit, preview, publish, revert; invalid or incomplete input refused with a clear reason; an unpublished species invisible to players.
**Definition of done for M2:** the artist adds a complete new species — every stage, stats, blurbs, library entry — alone, start to finish. If someone who doesn't code can't complete that walkthrough, it isn't finished.

**M2.4 — The rest of the content screens.**
Panel sections for **items**, **shops** (NPC art, dialogue lines, offerings), **postcards** (art plus its fixed message), and **themes**. Same conventions throughout: validate before saving, say plainly what's missing, preview before publishing, undo available.
Write the "how to add one" recipe in `docs/` for each, for a reader who has never programmed.

**M2.5 — Bulk entry for tables.**
Loot tables are hundreds of rows across twelve areas and four activities. Web forms one row at a time would be miserable and is where this kind of project stalls.
Build a **live editable table**: type or paste rows directly, with validation shown inline. Areas cannot be duplicated wholesale — drops are nearly unique per area — but the **category structure** (forage / hunt / mine / fish) can be duplicated as an empty scaffold so a new area starts with its shape rather than a blank page.
Tests: paste of multiple rows, inline validation, partial-save behaviour, that a malformed row is rejected without losing the rest.

**M2.6 — The unfinished list.**
A single generated view of what is incomplete: a species missing an adult sprite, an item with no icon, an area with no background, a shop with no dialogue. Nobody should have to hold this list in their head or ask another person.
Tests: each incompleteness type detected and cleared correctly.

**M2.7 — Moderation tools.**
- **The report queue**, ordered by category priority, showing the reported content and its context.
- **Account history on one page** — every name, bio, card sent and past report for an account. Judging reports in isolation is how repeat offenders slip through: each single thing looks borderline, the pattern does not.
- **Actions:** dismiss, force-rename to a neutral default, warn, mute, suspend — each logged.
- **Official notices to players.** "Warn" currently has no way to reach anyone, since the site has no messaging. Build a **one-way notice channel**: the site can tell a player something, delivered as a card explaining what happened and pointing to the official email for appeals. Players cannot reply. Without this, the only options are silence or suspension, with nothing in between.
Tests: each action, the audit trail, notice delivery, and that a player cannot reply to or spoof a notice.

**M2.8 — Site settings and safe maintenance.**
Named config values editable with an explanation beside each — cooldowns, XP thresholds, daily limits, click rewards, registration flag, the word blocklist. Tuning the game's feel must not need a deploy.
A **small fixed set of named maintenance buttons** for specific jobs (re-run seeds, clear cache, rebuild a derived value), each confirmed and audited.
**Do not build a terminal, command runner, or query console.** A web interface that executes arbitrary input is the single most dangerous thing that can exist on a public site: whoever reaches it owns the server, the database and every player's data. If a maintenance need appears that the named buttons don't cover, add another named button — never a general-purpose escape hatch. Not negotiable, not to be revisited.

**M2.9 — The stats page.**
What's being adopted, bought and visited; how much currency exists; which areas get used. This replaces a manually maintained spreadsheet and turns balancing from guesswork into a decision. It is also the artist's marketing information.
Keep it aggregate — this is about the game's health, not about surveilling individuals.

**M2 CHECKPOINT + FIRST SECURITY REVIEW.**
The artist can add and change content alone. **This is the point for the first external security review**, before five further milestones are built on top of this code. Roles, privileged actions and file upload are the highest-risk surface in the project; a review here finds *architectural* problems while they are still cheap. Scope it to: the role and permission model, admin route authorisation, the upload path, and the audit log's completeness.

---

## M3 — Rehoming, and the character a creature carries

*Renamed from "Rehoming (the pound)". The pound is still the centre of it, but
this milestone is now also where a creature stops being a species with a name and
starts being somebody — traits, a title, an age you can read, and the ability to
keep it at a stage you love.*

**M3.1 — The pound.**
Send a creature to the pound; another player adopts it. Required:
- A **three-day grace period** during which it shows as being processed, cannot be adopted, and **can be reclaimed by its original owner** — people misclick and a beloved creature should not be lost to one.
- After the grace period the original owner **cannot re-adopt their own** creature, protecting the M3.2 trait from being farmed.
- Presentation as designed: forbidding outside, warm and cosy inside. The contrast is the point.
Tests: grace boundaries, reclaim, adoption transfer, self-adoption block.

**M3.2 — Personality traits, in two tiers.**
A creature's character comes from two different places, and keeping them apart is the whole design:

- **Personality traits are earned by living.** They arrive as a creature grows and is cared for — the player can see how they were come by, and they read as a record of what this creature has been through.
- **System traits are given by the world.** The player does not earn these and cannot choose them. A creature released from the pound picks up **one or two shelter traits**; **"grateful"** is a *chance* reward on adoption, and is the one trait that carries a small **XP bonus**.

Both tiers are **data, editable in the panel** (principle (b2): a new kind of content is not finished until the panel can manage it, in the same milestone).

**Traits are flavour, plus that single XP bonus. Nothing else.** No trait changes a stat, a price, a cooldown or an outcome.

> **Deferred on purpose, and written down so it is not quietly forgotten: combat stat modifiers.** The old prototype hung five combat stats off every growth stage. Attaching numbers to traits before the arena exists would mean designing a combat system by accident, one trait at a time, and then being stuck with it. **Traits gain stat effects only when the arena is designed (M13), if that design wants them.** Until then, adding a stat to a trait is a decision that needs raising, not a small edit.

Keep the bonus small and the negative traits flavourful and temporary. They exist to give an adopted creature a history, not to make it a worse creature — a player who adopts from the pound should never end up wishing they had not.

**M3.2b — Aging freeze, with a catch-up queue.**
An owner can **freeze a creature at a stage they love**. Freezing stops growth; unfreezing **replays the missed stages, one per growth tick afterwards**, so nothing is lost and nothing arrives all at once.

Why the queue rather than simply jumping to where the creature "should" be: a player who freezes a baby for six months and then unfreezes should get to watch it grow up, not find an adult in its place. The missed stages are a queue of pending evolutions, not a number to catch up to.

**Fully reversible, no progression loss, and freezing is never punished** — a frozen creature still earns its owner currency when petted and still gains happiness. Freezing is about keeping a shape you love, not opting out of the game.

*Placement note: this sits in M3 rather than M8 (care) because it is a property of the creature's identity — what it IS — rather than of looking after it. It also shares its machinery with the pound: both need a creature's growth to pause and resume cleanly.*

**M3.2c — Level titles and a readable age.**
Two small ladders, **both held as data and both editable in the panel**:

- **Level titles** — a word for where a creature is, so "level 7" also reads as something. Shown wherever a level is shown.
- **A friendly age ladder** — "a few days old", "getting on for a year" — instead of a raw date or a day count. Shown wherever a creature's age is shown.

They are listed here rather than in a page-building milestone because they are **content, not layout**: adding a rung is a row, and every surface that already shows creature information picks them up at once.

**M3.3 — Reputation. [DISCUSS SCOPE BEFORE BUILDING]**
Adopting many creatures is looked on well; repeatedly abandoning them draws cooler NPC responses. The most socially delicate mechanic in the design document. Agree explicitly how harsh the negative side is before building. Default to light flavour rather than economic penalty, and ensure no player can be permanently locked out of anything.

---

## M4 — Themes and accessibility

**M4.1 — Selectable themes.**
Alternate palettes chosen by the player. Cheap because the site runs on one central file of CSS custom properties — a theme is a named set of those values plus a preference. Themes are data, addable through the panel.
**Hard constraint: every theme independently meets the contrast requirements.** A selectable theme that fails accessibility is worse than having no themes.

**M4.2 — Contrast checking in the panel.**
The theme editor checks contrast **while colours are being picked** and warns before an unreadable theme can be published. Judging contrast by eye is genuinely hard; making the tool do it means no theme ever ships broken and the artist never has to think about it.
Tests: a deliberately low-contrast combination is flagged and cannot be published.

**M4.3 — A dyslexia-friendly mode.**
Offered as its own theme: a suitable typeface, increased letter and line spacing, generous line height, and shorter measure. Real value for a real group, and cheap on top of the theme system.

**M4 CHECKPOINT + FIRST ACCESSIBILITY REVIEW.**
**Two people review accessibility here**, because themes and the dyslexia mode are the features most likely to break readability, and enough of the site now exists to test properly. Scope: contrast in every theme, keyboard navigation end to end, zoom to 200%, screen-reader passage through a creature page and the collection, touch targets on a real phone. **Include the panel itself** — the artist uses it several times a week, and it deserves the same standard as the player-facing pages.

---

## M5 — The library

The site's pokédex: how players work out what exists and what to hope for. It reuses creature data and the panel screen already built, so it is cheaper than it looks.

**M5.1 — Species pages.**
A page per species showing every stage with its **short blurb**, plus the longer lore. Reachable from a creature's page and from a browsable index.

**M5.2 — Library books.**
The presentation the artist described: a book graphic per creature, opened to read the full story. Book art is uploaded per species through the same one-page-per-creature screen.
Keep it usable as well as pretty: the book is a visual frame around ordinary readable text, never an image of text, and it works on a phone and by keyboard.

**M5.3 — Alternate forms.**
Multiple forms per species. Sprites currently resolve as `species/stage`; this adds a form axis. **Do it now rather than later** — a naming-convention and seed change today, a migration across every art asset if deferred. Which species get alternates is entirely the artist's call and pure data. Fall back to the default form when a species has only one.

**M5.4 — NPC articles.**
Newspaper-style articles about the NPCs, authored in the panel. Same shape as a library entry with different art. This is also where announcements and event write-ups can live later.

---

## M6 — Postcards, bottles, and company

Built on one finding: **the risk in player contact is not strangers, it is privacy plus free text.** Every documented grooming sequence needs a private channel and words the adult chooses. Remove both and stranger contact stops being dangerous — which is why the safest games for young players restrict communication to menus of pre-written phrases, and why this site needs no team watching conversations: there are none.

Rules for the whole milestone: every word between players was written by the project; nothing is private; **no feature is locked behind having friends.**

**M6.1 — Friends as a shortcut, not a gate.**
A bookmark list of players whose creatures you like visiting, kept somewhere easy so you can pet them and send cards without searching each time.
**It must unlock nothing.** Adding someone grants no access, which is why it needs no approval — exactly like bookmarking a page. Everything reachable through the list is reachable without it, just with more clicks. If a later feature is ever proposed as "friends only", that is a design change needing a fresh decision.
Also: remove a friend, and block, which stops cards and bottles from that account.
Tests: adding requires no consent and grants no permission; every friends-list action possible without the list; block enforcement.

**M6.2 — Clicking each other's creatures.**
Rework the petting reward so **both sides gain**: the owner's creature gets the growth, the visitor earns gems for caring for it. Logged-out visitors get nothing.
Currently only the owner benefits, which makes the friendliest activity on the site the only one that gives the doer nothing. This is the loop that historically made sites like this grow.
**Guard against alt-account farming explicitly** — per-actor cooldowns, a per-creature-per-day cap on rewarded clicks, and no reward where actor and owner are plainly the same person. State in the plan how you have limited it.
Tests: reward paid once per actor per creature per window, no reward when logged out, cap enforcement, and that repeated or concurrent requests cannot double-pay.

**M6.3 — The share box.**
A copy-paste box on every creature page containing ready-made HTML (and a plain-link alternative) showing the creature's image linked to its page, for posting on forums and other sites. The site generates the markup; players never write it.
Tests: generated markup is well-formed and contains nothing user-authored beyond the creature's name, correctly escaped.

**M6.4 — The post office and sending a card.**
Cards are items bought from a shop, reusing shop, item and inventory systems. Each is a piece of art carrying a **fixed pre-authored message**.
Sending: open your cards, tap one, tap who it goes to, send. **Cards can be sent to anyone, not only friends** — this is what stops the feature punishing people who know nobody. Rate-limited so nobody can be flooded.
**Received cards display publicly on the recipient's profile**, like a mantelpiece. Deliberate on two counts: cosier than a private inbox, and one player showering another with cards becomes visible rather than hidden — the pattern is the risk, not the words.
**UI:** no dropdowns. Cards are pictures, so a grid of tappable tiles; the recipient likewise a short tappable list.
**Card text is rendered by the site over the art, not drawn into it** — so one design can carry different messages, text stays sharp at any size, it can be edited without redrawing, and screen readers can read it. Card art should leave a calm area for text.

**M6.5 — Message in a bottle.**
Choose a pre-written message, seal it, set it adrift; someone finds it at random. They can then bookmark the sender and send a card back.
**Why this is the safest contact on the site:** nobody chooses who receives their bottle and nobody chooses whose bottle they find. The "pick a target" step that begins every approach pattern is not forbidden — it is simply not offered. Preserve that absolutely: no filtering, no browsing, no choosing, no re-rolling.
Build the simple form now — bottles wash up somewhere checkable once a day, with a small daily send limit. In M10 they also start appearing while fishing and gathering.
**A safety point belonging to whoever writes the messages:** even without typing, *which* message someone picks carries meaning. If the set contained anything signalling loneliness, age or personal circumstances, someone could open bottles until they found a vulnerable sender — exactly the targeting randomness was meant to remove. **The set stays warm and cheerful and never confessional.** Record this beside the message data so it survives whoever edits it later.
A found bottle can be reported and its sender blocked, from the bottle itself.

**M6.6 — Reporting across everything. [Definition of done for M6]**
Extend the M1.4 reporting and the M2.7 queue to cover names, bios, guestbook entries, cards and bottles. One consistent control, one queue, one audit log.
Card and bottle reports matter more than they look: a fixed message set can still be misused by **pattern** — the same card sent to one person repeatedly is harassment even when the words are kind. A recipient must be able to report a **sender**, not only a message, and blocking must be reachable from the thing that arrived.

---

## M7 — Opening up

**M7.1 — Security review. [EXTERNAL, MULTIPLE REVIEWERS]**
The full pre-launch review: authentication, sessions, access control, rate limiting, secrets, dependency vulnerabilities, CSRF and authorisation on every state-changing endpoint, the upload path, the admin surface, and the economy for duplication and farming exploits. Fix what it finds **before** anything below happens.

**M7.2 — Data protection.**
Privacy policy stating what is collected and why; account deletion that actually deletes; data export if required; cookie and session disclosure; where data lives and how it is backed up. Operated from Germany, so GDPR is binding. GDPR treats children's data with extra care, which ties directly to M7.3 — handle them together.

**M7.3 — Position on young players. [POLICY DECISION — made deliberately, not by omission]**
The design already does most of the work: no messaging, no free text, no private channel, no targeting. The position still has to be stated.
Agree and implement, with the humans deciding: a stated minimum age and how age is collected; **a written procedure** for a report suggesting an adult is approaching a child — who reviews it, how quickly, what the response is; a visible easy route for a player or parent to raise a concern or get an account deleted; confirmation that nothing on the site exposes location, contact details or real names, and that nothing added later may without revisiting this.

**M7.4 — Accessibility review. [EXTERNAL, TWO REVIEWERS]**
The second review, now against the finished Part One. Scope as at M4, plus the newer surfaces: profiles, library, cards, bottles, the report flow. Fix what it finds before opening.

**M7.5 — The gentle first run.**
Not a tutorial with pop-ups. Ruinily speaks in chat bubbles as a player arrives — name your creature, pet it, here's my shop, here's where you can wander — woven into her existing welcome rather than layered over it.
**A permanent "don't show me this again" control**, honoured forever, reachable from the first bubble. People who know what they're doing must be able to leave immediately without hunting for the exit.
Tests: the flow can be dismissed at any point, dismissal persists, and nothing in it blocks or gates ordinary play.

**M7.6 — Feedback route.**
A visible way to send feedback (the official email) alongside reporting, so suggestions don't arrive as false reports.

**M7.7 — Open registration.**
Flip the flag, with a soft opening: a small first group, watch, then widen. Document the rollback so turning registration off again is one config change.

**M7 CHECKPOINT — THE REAL LAUNCH.**

---

# PART TWO — Growing the world (live)

## M8 — Care

Creatures should feel complete when people meet them. Cheap to do here because care needs only **food items**, which the existing shop can sell — it does not wait on farming or cooking. **Build food as a data-driven property from the start** (an item restoring a care stat by an amount) so farmed and cooked food slots in later with no code change.

Tone matters: tending something you love, never a chore list with a guilt timer.

**M8.1 — Care stats and feeding.** Stats computed from timestamps, not a scheduled job. Feeding consumes a food item; roughly three feeds a day.
**Hard constraint: neglect never kills or permanently damages a creature.** A hungry creature looks sad; it does not die. Display with icons or short bars rather than numbers — gentler and better on a phone.

**M8.2 — Cleaning.** Mess appears on a timer; cleaning clears it.

**M8.3 — Sickness and cure.** A creature left unclean may fall ill; a care action or item cures it. Visible, temporary, never permanent, never cascading.

**M8.4 — The care bonus.**
A well-cared-for creature **earns double gems when clicked**. Deliberately the only care bonus: it flips care from an avoided downside into a reason, without stacking a pile of click multipliers that later cooking and items would compound.
Tests: the multiplier applies only while the care threshold is met, and cannot be combined into unbounded rewards.

**M8.5 — Badges and trophies.**
Small markers for long-kept creatures — 100 days and beyond — so a creature's individual story continues after it reaches adult. Badge art and thresholds are data, editable in the panel.

**M8.6 — Play and sleep. [DISCUSS BEFORE BUILDING]**
A simple play interaction granting happiness is a small increment; a minigame is its own project. Raise scope before starting.

---

## M9 — Shops and areas become plural

**M9.1 — Multi-shop engine.** A shop is data: slug, name, NPC portrait, welcome and intro lines, flavour lines, offerings. Build the shop index and the shop page with its dialogue box. Migrate the existing shop as the proof; seed Ruinily with the voice lines already written.
**Seasonal stock is already possible in the data.** Every shop listing carries an optional "available from" and "available to" date, added to the schema ahead of this milestone and inert until now. So Ruinily selling Valentine chocolates for one week in February, or the Gallows opening for Halloween, is a date on a row — not new code, and not a developer. Honour those dates when building the shop page, and give the panel two date fields when building its screen.

**M9.2 — Multi-area world.** An area is data: slug, name, background, description, supported activities. Build the area index — a world view that reads well on a phone — and make the existing area one entry among several. Seed the twelve areas with names and descriptions; loot comes in M10. Areas without content show an honest "nothing here yet" rather than faking interactivity.

---

## M10 — The world opens up

**M10.1 — Tools and gating.** Tools are items whose ownership unlocks an activity in the areas it applies to: fishing tools 1–7 from trap pot to diving chamber, mining 1–4, hunting 1–4. Foraging needs no tool and exists nearly everywhere.
The "you need X here" state is inviting, never a dead end: name the tool, say who sells it, link there.

**M10.2 — The gathering engine.** Area + activity type + required tool + weighted loot table + per-visit limit and refresh window, all data. Build once, well.
Tests: weighted distribution, limit enforcement, refresh window, tool gating, correct item to correct owner.

**M10.3 — Weather.** A weighted per-area roll on a timer, shown on the area page, including night and the rare meteor. **Atmosphere only** to begin with — whether it should later modify loot is a separate decision to raise, not assume. Respect reduced-motion.

**M10.4 — Bottles in the world.** Bottles also turn up while fishing and gathering, alongside ordinary loot. Same mechanic, same rules, same message set; only the places they are found change. No way to influence which bottle is found.

**M10.5 — Content pass.** Load the design document's loot tables through the panel. This should be almost entirely data entry. If it isn't, M10.2 wasn't finished.

**M10.6 — The fish tank.**
Placed here deliberately: until it exists, a caught fish is just something to sell, which makes fishing a money faucet. The tank turns a fish someone named and kept into something they can display.
A tank is a saved layout of positioned objects — fish the player owns, plus decorations. Arrangement is persistent and visible on their profile.
Decorations here are **ownable items**, the same as room and profile decoration in M11.4 — bought, owned, placed from a menu.
**Accessibility condition, non-negotiable:** dragging is not the only way to arrange things. Drag-and-drop is among the worst patterns for keyboard users, screen-reader users and anyone with limited fine motor control, and on a phone it fights with scrolling. Build **tap-to-select then tap-to-place** as a fully equal path, working by keyboard and touch, with dragging layered on top as a convenience. If only one can be built, build the tapping one — it works for everyone, including people who can drag.

---

## M11 — Crafting and the home hub

**M11.1 — The recipe engine.** Required station, input items and quantities, output, optional time or click cost. Validation says **what** is missing ("you need 2 more onions"), never merely refuses. A failed craft consumes nothing.

**M11.2 — Cooking (Yumie).** First consumer; recipes seeded from the design document.

**M11.3 — Potions (Luca).** Second consumer and the proof the engine is real: a different station and recipe set should need **almost no new code**. If it does, M11.1 was under-built.

**M11.4 — The home hub.**
The player's house as something they build up: once the stations are earned, they craft at home instead of borrowing the shops'. A house is shown on their profile and, where it fits, on the world map.
Reuse the recipe engine's station concept — an owned-unlock check, not a parallel system. Keep the progression gentle: the shops remain usable, so the hub is a comfort rather than a requirement.

> **How decoration gets built, decided in advance.** Decorating a room or a profile is done with **ownable items** — bought or found, owned in the inventory, and placed from a menu — reusing the item system that already exists. It is explicitly **not** the old prototype's free-form stack of stickers, overlays and font pickers laid over a page.
>
> Two reasons. A free-form canvas is its own year-long project and lets a player build a page that breaks on a phone or fails contrast. And decoration-as-items means every piece already has a price, a picture, a category and a place in the shops, so the artist adds one the same way she adds anything else.
>
> **The actual design happens when this milestone starts** — this note fixes the approach, not the screens. One thing is settled now though, because it is an accessibility requirement rather than a design preference: **placement must work by tapping and by keyboard.** Dragging may be added on top as a convenience, never as the only way (same rule as the fish tank, M10.6).

---

## M12 — Farming

**M12.1 — The plant lifecycle.** Named stages with click costs in data, per the design document's tables. Planting consumes a seed.

**M12.2 — Daily growth and fertiliser.** Every plant gains one click per day automatically, so people who visit rarely — or simply don't enjoy clicking — still grow things. Fertiliser raises it to two.
**This is a kindness feature, not an economy feature.** The free daily click must carry a plant to harvest alone; fertiliser is a shortcut, never a requirement. Accrual computed from timestamps, not a scheduled job.

**M12.3 — Harvest.** A flowering plant yields 1–3 produce and 0–1 seed. Whether it then dies, keeps bearing, or can be left indefinitely is per plant type in data.

---

## M13 — The long tail [each item needs its own decision]

- **Quests (Yuni).** Kept last deliberately: by then the world is built, the guild content released, and quests can tie it together rather than pointing at empty rooms. Data-driven — requirements, reward, dialogue — and the best value here.
- **Beachcombing (Wurari)** and the remaining themed shops — mostly data if M9 and M10 were done properly.
- **The arena.** The design document offers six possible systems and does not choose. **A choice must be made before any code.** Contest and intimidation styles are the gentlest and best match this site's tone — on a site whose fish are explicitly caught unharmed, a health-bar combat system would be a tonal break. A whole project in itself.
- **The dungeon and the spaceship adventure.** Sequenced content on the quest engine, if it exists.
- **Breeding** in its player-to-player form is excluded (see appendix). A solo version — two creatures one player owns — raises no such concern but is a substantial mechanic needing its own decision.

---

## Appendix — Deliberately excluded: trading and complex player interaction

**A decision, not an omission.**

Trading is a direct player-to-player interaction in which one person offers another something rare and desirable. That is a documented approach pattern toward young players — the same risk closed by removing messaging — and it carries the scam, duplication and real-money-selling problems that make it the highest-risk feature on any pet site.

The same reasoning rules out, for now: **gifting arbitrary items** (as opposed to the fixed postcards), **breeding between two players' creatures**, **auction houses or player shops**, and any mechanic where two players negotiate or exchange freely.

**The line this project holds:** players may send each other warmth through things the project authored — a pre-made card, a pre-written guestbook line, a click on someone's creature. They may not exchange value, and they may not exchange words of their own. Check any future feature request against that line first.

**Status: not now, revisit only if the community actually asks.** Left open rather than closed forever — if players start asking for a way to pass things between each other, it gets reconsidered then, with real usage to reason about. Until then it is not built and not planned. If revisited, it starts as a fresh safety decision by the humans, not a build task.

---

## Documentation you keep producing

Alongside each increment, never at the end. This stage adds a growing set of **"how to add one"** recipes in `docs/` — a shop, an area, an activity, a recipe, a plant, an item, a theme, an alternate form, a library entry, a card, a quest. These are the most valuable documents in the project: they are what lets content grow without a developer. Write them for a reader who has never programmed.

## Honest note on pace

Part One is the part with real stakes, because it is what stands between the site and actual players, and it contains two external reviews. If time pressure appears, move the opening date rather than thinning M7.

Part Two has no deadline. Each milestone is a stopping point, and stopping at one is a success.

## First action

Do not start coding. First tell the human whether the outstanding Phase D deployment work should be closed before M1 — you can see the repository state.

Then produce the written plan for **M1.1** per the standard workflow, including the owned-thing model proposal with options, honest trade-offs and a recommendation. Present it for sign-off before building anything.
