# The new face — sidebar, banner, and a starry sky

*Planned 2026-08-14, before any code. The artist has drawn a new layout: a
parchment sidebar for "you", a wide main panel for "the world", both floating on
a hand-painted starfield. This document settles the open questions in that
mockup (where the browse/users buttons live, what happens on a phone, where the
creature message goes) before a line of the shell changes. Every decision is
looked at through three pairs of eyes: the artist's intent (does it keep the
crude, human-made, old-web warmth?), usability (can everyone use it, one thumb,
small screen?), and the rules in CLAUDE.md section 8.*

---

## What the artist drew, in words

Two floating parchment panels on a dark field. On the left, a slim sidebar:
your name in the display serif, your gem purse as a dark chip, your avatar in a
framed box, three stacked pill buttons (home, my creatures, inventory), a quiet
"log out", and your favourite creature in a gold-edged card with a FAVOURITE
tag. On the right, the wide panel: a small server clock in the top corner, the
painted banner across the top, a row of pill buttons under it, a divider, then
the page itself, ending in a small footer line (back to top · ©). The demo
notice floats above everything as its own dark strip.

New since the mockup: `background.webp`, a hand-painted tiling starfield
(black space, magenta nebula clouds, a blue star-river, gold sparkles). It
replaces the flat plum gradient as the page field — the artist did exactly this
in a previous iteration of the site, and it is the single strongest "personal
old-web page" signal the design has.

## The three open questions, decided

**1. Where do "browse creatures" and "find people" live?** The artist wasn't
sure: important, but a two-button task bar feels thin. Decision: the split is
*you* versus *the world*. The sidebar is everything that is yours — home, my
creatures, inventory, your page (the avatar itself is the door), log out. The
bar under the banner is every place in the world you can go: **shop · explore ·
adopt · browse creatures · find people**. That gives the task bar five buttons
(not two), gives each button an obvious reason to be where it is, and neither
list needs group labels any more — five is glanceable, the old nine was not.

**2. The favourite creature may be hidden on a smaller screen.** Accepted,
deliberately. The sidebar card is a keepsake, not a control — the same creature
is one tap away on the home page and on your own page. Below the desktop
breakpoint it simply isn't rendered visible; nothing is lost, nothing is
duplicated into precious phone height.

**3. The creature message goes over the banner.** As the artist drew it: on a
wide screen the moment is absolutely positioned over the banner from the top
left, portrait seated on a parchment circle so it reads against the busy art,
bubble reaching across — a proper little popup, and nothing important is under
it (the banner is decoration; its name lives in the alt text). On a phone it
stays in the flow above the page content exactly as today — overlaying it there
would cover the small banner and the buttons. Markup does not move; only CSS
changes between the two.

## The phone problem, solved by subtraction

Today a phone lands on a 208px square logo, a helper line, the purse, then nine
grouped nav pills — half a screen of chrome on *every page*. The artist called
it "okay at the moment, but it may get tedious." She is right. The fix:

- **The wide banner everywhere.** The 800×150 banner at 360px wide is ~67px
  tall — a warm little masthead instead of a landing page. The square badge and
  its "Creatures" helper line retire from the layout. (`<picture>` goes; one
  `<img>` stays.)
- **The sidebar becomes a compact identity strip** at the top: name and purse
  on one line, the three personal pills plus log out on the next, avatar small
  beside the name. Favourite creature: hidden (decision 2).
- **The world bar is one wrapping row of five small pills.**

Chrome before content on a 360px phone drops from roughly 500px to roughly
280px, and every destination is still one tap — no menu, no hamburger, no
dropdown (section 8 stands).

## The palette, tuned to the sky

The starfield's clouds are distinctly more magenta than our accent purple. One
token moves: `--purple #6A4C93 → #7B3FA0` (orchid). Checked before choosing:
5.43:1 on parchment and 4.70:1 on panel — within 0.01 of the current purple's
ratios, so every existing AA pass still passes. Everything else holds: plum
stays the ink (10.03:1), gold stays the sparkle (and the painted stars are
gold, so it now rhymes with the sky), parchment stays the surface. One derived
token is added: `--night #0E081A`, the fallback colour behind the background
image (parchment on night: 15.6:1). The body loses its plum gradient and gains
the tiled painting. CLAUDE.md's palette section is amended in the same commit —
the contract changes deliberately, not by drift.

## Small whimsy, kept from the mockup

- **The server clock** ("17:35, Aug 13th") in Space Mono in the panel's top
  corner — the classic petsite "server time" touch. Pure decoration, one line
  of PHP `date()`, no JavaScript.
- **"Back to top"** in the footer — an anchor link, honest and old-web. The
  footer's faq/tos/credits links from the mockup are *not* added: those pages
  don't exist, and a dead link is worse than no link (golden rule 3).
- The drifting gold motes stay — they now fall in front of a real sky.

## How it works (files)

- `templates/layout.php` — becomes the shell: demo notice, `.site-shell` grid,
  inserts the new sidebar partial, main panel with clock + banner + world nav +
  content + footer. Shrinks; stays under 200 lines.
- `templates/partials/site-side.php` — **new**: the sidebar (identity, personal
  nav, log out form, favourite creature via the existing `creature-card`
  partial; guest variant shows the kettle tagline and log in / sign up).
- `templates/partials/site-nav.php` — rewritten as the flat five-pill world bar
  (guests: browse creatures only). Group labels retire with the grouping.
- `public/index.php` — the owner's creature summaries are already fetched every
  logged-in request for the moment roll; they are computed once into a variable
  and the favourite (`isFeatured()`) picked from them at no extra query. A
  `ProfileRepository` lookup (already-built class, constructed earlier) supplies
  the avatar key; `AvatarSet` turns it into a path + name for the sidebar
  `<img>` alt.
- CSS: `theme.css` (tokens + body background), `layout.css` (shell grid,
  panels, banner, clock, footer), `site-nav.css` (world bar, moment overlay),
  **new** `sidebar.css` (the sidebar, keeping every file under 400 lines).
- `background.webp` moves to `public/assets/art/background.webp`.

Desktop breakpoint for the sidebar: **900px** (a 260px sidebar plus a readable
panel need more than the old 640px). The 640px queries that only concerned the
retired square logo and absolute purse go away.

## Security

No new endpoints, no new input, no state changes. The sidebar renders only the
logged-in player's own data (avatar key through the `AvatarSet` allow-list, as
everywhere; names escaped by Plates on output, as everywhere). The log-out form
keeps its CSRF token. The clock prints the server's own `date()` — nothing from
the request. Worst a malicious user can do: nothing they couldn't do to the old
layout.

## Test cases

1. `vendor/bin/phpunit` — all existing tests, especially
   `CategoryContrastTest` (parses theme.css; the tint tokens are untouched).
2. `php bin/smoke-test.php` — walks every page logged-out and logged-in; its
   markup checks (every `<img>` has `alt`, no repeated ids, no skipped heading
   levels) cover the new sidebar avatar, banner, and favourite card.
3. Hand-checks on the running site at ~360px and ~1200px: sidebar order, world
   bar wrap, moment overlay only at desktop, favourite card only at desktop,
   focus rings on every pill, reduced-motion still calm.
4. Contrast arithmetic for the changed token, recorded above, done before the
   value was chosen.

## Left out on purpose

- **No sticky/floating anything on mobile.** A sticky nav eats phone height,
  the exact thing this pass gives back.
- **No faq/tos/credits footer links** until those pages exist.
- **No hamburger, no collapsing menu.** Five pills wrap; wrapping is fine.
- **No second copy of the favourite creature for phones.**
