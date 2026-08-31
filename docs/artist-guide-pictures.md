# Pictures — a guide for the artist

This is for Skerro, and for anyone who draws for Felkyo. No code words. It
explains how to get a picture into the site, what the site does with it,
and the handful of things to get right when you export.

---

## Where pictures go

Open the panel (**the panel** in your sidebar, or the second row of buttons
on a phone), pass the door, and choose **Pictures**. That is the art store —
every picture the site can show lives there. Upload sprites, item icons,
NPC portraits, card art, area backgrounds and library art from the same
form; the creature pages will pick from this shelf.

**To upload:** choose the file, pick what kind of picture it is (one of the
six cards), press **Upload it**. The site tells you in a sentence what
happened — "bramble-adult.gif is in — a creature sprite, 64×48, animated" —
or, if it couldn't take the file, exactly why.

**To delete:** every picture you uploaded has a small **delete** link under
it. It asks you to confirm. An owner can delete any picture; an artist can
delete their own.

**Same picture twice?** Nothing happens — the site notices it already has
those exact bytes and tells you so. It never keeps two copies.

## What the site keeps, and what it removes

- **Every pixel, every frame, every frame delay, and the loop setting are
  kept exactly as you exported them.** The site never redraws an animated
  file.
- **Notes hidden inside the file are removed.** Export tools sometimes tuck
  a comment, a software name or an author tag into the file. Those are
  stripped on upload; nothing about the picture changes.
- **Still PNGs are re-saved cleanly.** Same pixels, smaller file, no hidden
  notes.
- **The file's name is only a label.** The site stores the picture under a
  name it makes itself. Your filename is shown in the grid so you can find
  it, that's all — so name files however helps you.

## The rules of the door

- **GIF, PNG or WebP.** Nothing else — JPEG has no transparency and cannot
  animate, so it isn't offered.
- **Up to 5 MB per file** and **2048 × 2048 pixels**. A sprite is a few
  hundred KB; a full-width background fits comfortably.
- If a file is refused, the sentence on screen says why. "Too big", "not a
  GIF, PNG or WebP", "cut short" (the export didn't finish), or "damaged".

## Exporting animation — the eight things that matter

These are the ones the site cannot fix afterwards, because it never
redraws your file:

1. **Export at the creature's real pixel size.** Don't scale up on export.
   The site enlarges pixel art by whole steps itself and keeps it crisp; an
   enlarged export gets enlarged again and goes soft.
2. **Set the animation to loop forever.** In most tools this is "loop:
   forever", "infinite", or a loop count of 0. A GIF exported to play once
   will play once on the site.
3. **Keep every frame delay at 20 ms or more** — 50 to 100 ms per frame is
   the usual pixel-art range. Browsers treat anything shorter than about
   20 ms as 100 ms, so a "fast" animation suddenly crawls.
4. **Use real transparency, not a coloured square.** A transparent
   background shows the parchment through it; a baked-in white or black
   box cannot be removed.
5. **Stay under 5 MB and 2048 × 2048.**
6. **GIF is 256 colours per frame; animated WebP is not.** For a painterly
   palette, WebP keeps every colour and is usually smaller. Both are
   accepted and treated the same.
7. **Don't put notes in the file's comment field** — they're removed on
   upload anyway. Notes about a picture belong in the panel.
8. **Keep your originals.** The site's copy is backed up, but the layered
   source files exist only where you keep them.

## If something looks wrong

- **The picture shows but doesn't move:** the export wasn't animated, or
  was set to loop once (rule 2).
- **It moves but too slowly:** frame delays under 20 ms (rule 3).
- **It's blurry:** exported larger than native size (rule 1).
- **There's a coloured box around it:** no real transparency (rule 4).
- **"Too big":** over 5 MB or 2048 pixels (rule 5). If a file well under
  that is still refused as too big, tell a coder — the server may need its
  own limit raised.
