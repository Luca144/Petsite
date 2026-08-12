# The owned-thing model

*Written for increment M1.1. This is the decision about how Felkyo stores
everything a player owns. If you are adding a new kind of thing players can have,
this is the document to read first — the recipe is in section 5.*

---

## 1. The question this answers

Players will eventually own four kinds of thing:

| | Examples | What it has |
| --- | --- | --- |
| **Items** | treats, stickers, seeds, postcards, tools | name, type, price, sell value, picture |
| **Creatures** | Biscuit the mossling | a name its owner chose, a species, a birthday, times petted, a mood |
| **Plants** *(M12)* | a growing onion | a name, a type, a growing time, clicks |
| **Fish** *(M10)* | a fish someone caught and kept | a name, a breed, when it was caught, a size |

Four kinds. But **only two shapes**, and telling them apart is the whole idea.

## 2. The two shapes

**Counted things.** One honey treat is exactly as good as any other honey treat.
Nobody names them, nobody minds which one they get, and "I have three" says
everything worth saying. So we store a **count**: this player has three of that
item. Two tables do the work — `items` says what a honey treat *is*, and
`inventory` says who has how many.

**Individual things.** Biscuit is not interchangeable with another mossling, even
an identical one. Biscuit has a name somebody chose, a birthday, and a history of
being petted. So Biscuit gets **a row of her own**, with an identity nothing else
shares.

Plants and fish are individual things, not counted things. That is not obvious at
first glance — a fish sounds like something you might have five of — but the build
plan settles it: a player names their fish, and later arranges particular fish in
a tank (M10.6). You cannot put "3 × trout" in a specific corner of a tank. It has
to be *that* trout.

**The test, when you are unsure:** *could two of these ever need to be told apart?*
If a player would name it, decorate with it, or be upset to lose the specific one,
it is individual. If they would only ever count them, it is counted.

## 3. What we decided, and what we turned down

**The decision: each individual kind gets its own table.** `creatures` exists
already; `fish` and `plants` get built when their milestones arrive. Counted
things keep the `items` + `inventory` pair they already use.

Two other designs were considered properly, and it is worth knowing why they lost,
because both sound tidier than the one we chose.

**"Put everything in one table with a `kind` column."** Tempting, because then
"show me everything this player owns" is one simple query. The problem is that the
kinds need different information, so the table ends up either full of columns that
are empty most of the time, or with one flexible column holding a jumble that
nothing can check. The database stops being able to help you: nothing stops a
creature pointing at a species that does not exist, nothing insists a fish has a
catch date, and — worst for whoever reads this next — nothing in the table *tells
you what a fish has*. You would find out by reading every query that touches one.
We build the opposite way round here on purpose.

**"One shared table for the common parts, plus a detail table per kind."** This is
a real and respectable design, and its advantage is real too: everything owned
would share one set of id numbers, so a future feature that has to point at "any
owned thing" would have one place to point. We turned it down for three reasons.
Reading one creature would become a join across two tables, and creating one would
become two inserts that must both succeed. It would mean rebuilding the live
`creatures` table, which `pettings` and `guestbook_entries` both depend on — a
large, risky change to a site that is already running, in exchange for a benefit
nothing needs yet. And "why does one creature live in two tables?" has no short
answer, which matters in a codebase somebody is learning to code from.

**The one thing we gave up, and how it gets handled.** The fish tank (M10.6) holds
fish *and* decorations, which are items — two different kinds in one list. With
separate tables there is no single id to point at. The answer is not to redesign
the whole model for one screen: the tank's table gets two columns, `fish_id` and
`item_id`, with exactly one of them filled in per slot. Both stay real foreign
keys, the database keeps checking them, and anyone reading it can see at a glance
what a slot may hold. Recorded here so it does not have to be rediscovered under
time pressure during M10.

## 4. The rules every owned thing follows

### Rule 1 — the same columns, named the same way

Every table holding individual owned things has these, spelled exactly like this:

- `owner_id` — who it belongs to; a foreign key to `users.id`, deleted with them
- `name` — what the owner called it
- `created_at` — when they got it (a creature's birthday is this column)

then whatever is specific to that kind. Separate tables that *read* the same are
much easier to live with than one clever table, and this convention is what makes
that true. Follow it even where a different name would have been slightly nicer.

### Rule 2 — the query says who is allowed, not the code around it

**Every method that reads or changes something a player owns takes the acting
player's id and puts it in the `WHERE` clause.** We do not fetch a row by its id
and then check the owner in PHP afterwards.

Both approaches work when they are written carefully. The difference is what
happens when they are *not*. A check written in PHP is a line that can be deleted,
moved during a tidy-up, or skipped by a new route somebody adds in a hurry — and a
missing `if` is invisible when you read the code, because there is nothing there
to see. An owner named in the query cannot go missing quietly: if it is wrong, the
statement matches nothing and changes nothing.

The realistic attack this prevents is dull and effective. Every form on the site
carries an id. Changing that id by hand takes about four seconds, and if the only
thing between a stranger and your creature is one `if` somebody forgot, they are
through. So the check lives where forgetting is not possible.

Keep the check in the controller as well. Layers are cheap; this is not a
competition between them.

`tests/Integration/OwnedThingOwnershipTest.php` proves it, and every test in it is
written from the stranger's side: *Rowan tries, and is refused.* Not "the owner can
do this." CLAUDE.md asks for the refusal to be tested, and the refusal is the part
that protects somebody.

### Rule 3 — two numbers, never one

An item has a `price` (what a shop charges) and a `sell_value` (what a player gets
back). They are separate columns and they must be, because **anything that sells
for more than it costs is a machine for making unlimited currency**: buy, sell,
repeat. One column doing both jobs would hide that rule; two named columns let us
write it down and test it.

`sell_value = 0` means **"no shop around here wants this"**, not "this sells for
nothing". That distinction earns its keep in what the site can say: an unsellable
thing can be explained kindly, instead of showing a sell button that takes
somebody's treat and hands back zero, which just feels broken.

New items default to 0 — not sellable until somebody decides what it is worth.
That is the safe direction to be wrong in. An item that quietly cannot be sold is
a small disappointment. An item that quietly sells for a fortune is an economy
nobody notices is broken until the currency is worthless.

`tests/Integration/ItemSellValueTest.php` checks every item every shop offers and
fails by name if one breaks the rule. It is guarding against a typo in the panel
far more than against a bug in the code.

**How much margin, and where the number came from.** "Not a loss" is not enough —
the shopkeeper has to live off the difference too, so a real margin is kept rather
than a hair's breadth. The ceiling is `maximum_sell_fraction_of_price` in
`config/config.php`, currently **80%**: a shop never pays more than four fifths of
what it charges.

That number is not invented. Across every item the artist had already priced in
the design document, the most generous buy-back was Pumpkin Soup at 75% — 60g to
buy, 45g back. So 80% leaves her room to be kind and breaks nothing she had
already written.

**This rule also covers a mechanic that does not exist yet.** The artist wants
players to be able to befriend the NPCs, which will make their shops cheaper
(M13). Friendship discounts open the same hole from the other direction: an item
costing 100g and selling back for 70g is safe until a well-liked shopkeeper takes
40% off, at which point it costs 60g and sells for 70g and the loop is open again.
Nobody would design that — it falls out of two reasonable numbers meeting.

So the rule is written to hold whatever the price becomes: **no discount may ever
take a buy price to or below what the same shop pays for the thing.** If
friendship would push it lower, it stops there. Sunny may like you very much; he
still will not sell at a loss.

Friendship is also expected to affect **buying only**, not selling. Two numbers
moving toward each other is twice as many ways to get it wrong, and "things are
cheaper for friends" reads more naturally than "friends pay you more" anyway. If
that ever changes, the single rule above still protects the economy — it just has
to be checked after both adjustments rather than one.

---

## 5. How to add a new kind of owned thing

*The recipe. Written for someone who has not done this before. Fish and plants are
the next two, and they follow it exactly.*

**Step 1 — decide which shape it is.** Ask the question from section 2: could two
of them ever need to be told apart? If not, stop — you do not need a new table at
all. Add a row to `items` with a new `type` and you are finished. Most new content
is this, and that is rather the point.

**Step 2 — write the migration.** Copy
`migrations/20260701120003_create_creatures_table.php` and change what it holds.
Keep `owner_id`, `name` and `created_at` exactly as they are (Rule 1), and add the
columns specific to your thing. Give `owner_id` a foreign key to `users.id` with
`CASCADE` on delete, so that when somebody deletes their account their things go
with them rather than sitting there ownerless. Index anything you will look things
up by.

**Step 3 — write the value object.** Copy `src/Creatures/Creature.php`. It is a
plain read-only class listing the columns, with a `fromRow()` that builds one from
a database row. No logic — it holds, it does not decide.

**Step 4 — write the repository.** Copy `src/Creatures/CreatureRepository.php`.
This is the only place SQL for your thing lives. **Every method takes the acting
player's id and puts it in the `WHERE` clause** (Rule 2). List your columns by
name in every `SELECT` — never `SELECT *`.

**Step 5 — write the ownership tests before anything else works.** Add your kind
to `tests/Integration/OwnedThingOwnershipTest.php`, in the same shape as the ones
already there: a stranger tries to read it, a stranger tries to change it, and both
are refused. Then the ordinary tests for what it does.

**Step 6 — give it a panel screen.** From M2 onward this is not optional and not a
follow-up: a new kind of content is not finished until the artist can create and
edit it without a developer. Same milestone, same definition of done as the tests.

**Step 7 — write it into `docs/schema.md`** in plain language, beside the others.

**Step 8 — add a line to the table in section 1 above.** The next person's first
question will be "what kinds of thing are there?", and this is where they will
look for the answer.

---

## 6. What deliberately does not exist yet

**There is no `fish` table and no `plants` table.** They are not forgotten — they
are waiting for M10 and M12, and for the artist's design document, which is where
their real attributes come from. Creating them now would mean guessing their
columns from a one-line summary and migrating them again once the real design
arrived. The model is decided; the tables follow the recipe when their milestones
do.

**There is no shared `OwnedThing` type in the PHP code.** One was planned for this
increment and dropped while building it, which is worth recording honestly. The
idea was a single type that creatures, fish, plants and item piles all satisfy, so
that one screen could render any of them. It fell over on first contact: a
`Creature` does not know where its own picture lives — the art path is built from
the species slug and the life stage, which come from elsewhere — so the shared type
could not have carried the one thing a shared screen most needs. And nothing yet
renders two kinds through one path, so it would have been an abstraction built for
an imagined caller. CLAUDE.md is direct about this: build for the extensions that
are clearly coming, not the hypothetical ones. If M1.2's item card and a later
creature card genuinely converge, the shared type can be extracted **then**, from
two real cases, which is the only way to get it right.
