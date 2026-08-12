# Free text, and how Felkyo keeps it safe

*Increment M1.4. The most important safety document in the project — read this
before touching anything a player can type into.*

---

## 1. Why this is such a small problem, and such a serious one

Felkyo has no messaging. Players cannot write in each other's guestbooks, cannot
write on cards, cannot talk. **Every word that passes between two players was
written by us.**

That leaves exactly three places where a player chooses their own words:

1. their **account name**
2. their creature's **name**
3. a **bio** or **about text**

Three fields. And because there is nothing else, those three carry the entire
weight that messaging would have carried. Everything in `src/Safety/` exists for
them.

## 2. The one thing that matters most

Of everything checked here, **the link filter is the most important**, and it is
worth being clear about why.

A stranger on this site can do very little. There is no private channel, no way to
choose who receives what, no words of their own to send. Someone who wants to
reach a child here has almost nothing to work with — **except one thing.** If they
can persuade somebody to leave, none of it applies any more.

A link, a username on another site, an email address: each is a door out of every
protection this project has, into somewhere with none of them.

So `ContactDetailDetector` is not about spam or tidiness. It is the lock on that
door.

## 3. Be honest about what filters achieve

**They are a raised floor, not a wall.** A determined adult will get something
past every check in here. That is true of every filter ever written, including the
ones with a hundred engineers behind them, and pretending otherwise would be worse
than not trying.

What they actually do, and it is worth valuing:

- **They stop the casual case completely.** Somebody pasting their profile link
  without thinking simply cannot.
- **They make the deliberate case look deliberate.** A person who writes
  `find me on d1sc0rd` has visibly worked at it. That turns a borderline judgement
  into an easy one for whoever reads the report — which matters enormously when
  the person reading it has a day job and twenty minutes.

**Reporting is the safety system. The filters exist to keep its volume
manageable.** If you only remember one sentence from this document, that is it.

## 4. When in doubt, refuse

A false positive costs somebody a rewritten sentence. A false negative can cost a
child. Those are not comparable, so the tuning leans towards refusing, and every
refusal says plainly what to change.

**But a filter that refuses ordinary sentences gets switched off, and then
protects nobody at all.** This is a real tension, not a slogan. While this was
being written, *"A gentle creature who loves naps."* was refused as advertising
Snapchat — because with the spaces removed, `lovesnaps` contains `snap`.

That is why `TextSafetyTest` tests the innocent sentences **first**, and why the
matching rules are more careful than they look:

| Form | What it catches | Why it is limited |
| --- | --- | --- |
| Normal text, whole words | `add me on snap` | — |
| Letter-spacing closed up | `s c a m`, `d i s c o r d` | Only runs of *single* letters, so real words are untouched |
| All spacing removed | long platform names | Only for names of 7+ letters, where an accidental collision is implausible |

## 5. What is actually checked

All of it lives in `TextGuard`, shared by all three fields. **Sharing it is the
point** — three separate sets of rules would mean three different sets of gaps,
and the gap nobody remembered would be the one that mattered.

- **Length**, so there is less room to hide something and less for a human to read.
- **Emptiness.** A name of spaces or invisible characters is a player nobody can
  point at, refer to, report or block.
- **Hiding characters** — zero-width spaces, bidirectional overrides. **Refused,
  not stripped**: nobody types a right-to-left override into a pet name by
  accident, and quietly cleaning it would hide that they tried.
- **Contact details** — see section 2.
- **Blocked words**, held as data in config. A blocklist catches the obvious and
  nothing more; do not let it grow into whack-a-mole, because every word added is
  a word somebody innocent will eventually be refused for.
- **Impersonation**, for names only.

### Impersonation, in two halves

**Pretending to be staff.** Someone called `Felkyo Admin` can ask another player
for their password and be believed — on a site with children, "the person from the
site said so" is enough. Refused by a word list.

**Pretending to be another player.** `mira`, `m1ra`, `rnira`, `mirа` (that last
has a Cyrillic а) are four strings and one name to anybody reading quickly.
`ImpersonationGuard` reduces each to a *skeleton* and compares skeletons.

The skeleton is stored on the account (`users.username_skeleton`, indexed) so this
is one lookup rather than a scan of every account. **That is not an optimisation
for its own sake** — a check that gets slower as the site grows is a check
somebody eventually removes.

## 6. Reporting

**A fixed list of reasons, no free-text box.** Two reasons:

- A "tell us more" field would be one player writing words another player reads,
  which is the one thing this site's design does not have.
- The reason drives triage. A rude creature name and an adult approaching a child
  must not sit in the same undifferentiated queue.

Reasons are offered **most serious first** — somebody frightened should not have
to read past "there's a rude word" to find the one describing what is happening to
them — and each is worded for an upset eleven-year-old, not for a moderator.

**One report per person per thing**, enforced by a unique index. Without it one
person could bury the queue; with it, "how many *different* people reported this"
becomes a meaningful number, which is the most useful signal a small team has.

**Every report is acknowledged.** Silence teaches people not to report, and
reporting is the whole safety system.

### Bios hide; names do not

A reported bio or about text disappears behind a neutral placeholder until
somebody looks. A reported name is only flagged.

| | Hidden? | Why |
| --- | --- | --- |
| Bio / about | Yes | Worst case is a hidden paragraph for an evening. The alternative is harmful text in public all night, with nobody awake. |
| Name | No | Hiding it would break every page it appears on — **and would let anybody erase another player from the site by reporting them.** |

**A known, deliberate risk:** because reporting hides a bio, somebody can hide an
innocent player's text out of spite. This is a trade, not an oversight. What limits
it: hiding needs a *different* person each time, and every report is stored with
its reporter.

> **This makes a requirement of M2.7, not an optional extra:** the moderation queue
> must surface people whose reports are always dismissed. Without that, this trade
> goes bad.

## 7. If you add a new field a player can type into

Then it belongs to this same category, and it is **not a small change**. Raise it
rather than adding it quietly. Concretely:

1. Run it through `TextGuard` — `checkName()` or `checkLongText()`.
2. Store the **cleaned value** from the result, never the raw input.
3. Add a `ReportSubject` case, and answer `hidesUntilReviewed()` deliberately.
4. Put a report link beside it wherever it is shown.
5. Add its innocent cases to `TextSafetyTest` — not only its refusals.
