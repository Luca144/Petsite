# Playtest scripts

One file per release, named by the date it was written.

## What these are for

The test suite proves the code is correct. `bin/smoke-test.php` proves the website
works. Neither can tell you whether a person **understands** it — and that is the
question CLAUDE.md's first golden rule asks: *if it needs explaining, it isn't
designed yet.*

A playtest script is how that gets answered. It is a list of things to ask somebody
to do, phrased as a goal rather than as instructions, plus a note for whoever is
watching about what going wrong would look like.

## How to run one

**Read the quoted sentence and then stop talking.** The whole method is not
helping. "Where do I click?" is not a question to answer — it is a finding to
write down.

**Write down hesitation, not just success.** A task completed after twenty
confused seconds is a failed task, and it will not show up in any other kind of
test we have.

## Why they are HTML and not Markdown

They get walked through with a phone in one hand, so they are laid out to be read
on one — and they have real tick-boxes for the checks. Each is also published as a
private page on claude.ai so it can be handed to a tester without them cloning the
repo; the copy here is the version-controlled original, so nothing depends on that
link surviving.

Open one straight from disk in a browser — they have no dependencies beyond the
Google Fonts link, and they still read fine without it.

## The scripts

| File | Covers |
| --- | --- |
| `2026-08-18-m2-playtest.html` | M2 — moods, treats, the keepsake card, the favourite star, buying creatures with gems, and Skerro's mobile notes |
