# Changing the site's look and words — without a developer

Everything here is in the panel. Every change goes in the audit log, and every
change can be undone.

## Words: any sentence on the site

**Quickest:** press **✎ edit words** in the sidebar (on any page). Sentences you
can change get a dotted line. Click one (or tab to it and press Enter), change
it, press **Save** — you land back on the same page with the new words.
**Back to the original** undoes it. Press **stop editing words** when you're done;
while editing mode is on, clicking a link edits its words instead of following it.

**Or:** panel → **The site's words**. Pick a page from the pills, or search.
"changed so far" lists everything that's been changed.

- `**two stars**` makes **bold**, `*one star*` makes *slanted*.
- Gaps like `{name}` or `{1}` are filled in on the day (a creature's name, a
  number). Move them or leave them out — but don't invent new ones; the site
  refuses those.
- **Messages** (what the site says after you do something — "Saved!", "Thank
  you!") are in the list under "messages", and clickable in editing mode when
  one is showing.
- Leaving a box empty puts the original words back.

## Look

**Panel → Themes** — each theme has colours (with a readability check) and a
**Style**: heading letters, reading letters, corners, frames (plain, stitched,
dotted), glow, what drifts down the page, and its own **background** and
**banner**. For your own background or banner, upload it first in **Pictures**
as **Site art**.

> A theme with "easy-reading letters" ticked always uses the Lexend font, so the
> letters you choose only show when that box is unticked.

**Panel → Site look**
- **The look everyone sees**: pick a published theme as the default (guests and
  everyone who hasn't picked their own). "Lamplight" is Felkyo's own look.
- **The order of things on a page**: move the parts of creature and player
  pages up and down.
- **An announcement**: one line at the top of every page. Empty = none.

**Panel → Fine-tuning CSS** — for small fixes ("this button is cut off on
phones"):
1. Write the CSS, press **Preview — just me**. Only you see it, on every page,
   with a dark bar reminding you.
2. Look around (try your phone too!). Then **Publish to everyone**, or
   **Stop previewing**.
3. Something went wrong? **History** → **Put this one back** on the last good
   version. Publishing an empty box switches it all off.

Not allowed (the site will say so): the `<` character, `@import`, backslash
escapes, and pictures from other websites — use `url(/art/...)` for your own.
Colours from the theme work as `var(--purple)`, `var(--gold)` and so on.
