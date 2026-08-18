# House patterns

*The handful of ways this project does certain things, and why. When one of these
applies, use it — do not invent a second way of doing the same job. A codebase with
two patterns for one problem has three problems.*

Each pattern below answers the same underlying question: **what happens when two
requests arrive at the same instant?** That is not a rare event. A double-tapped
button on a slow phone does it, a browser retrying a timed-out request does it, and
anybody deliberately trying to get something for free does it on purpose.

---

## 1. Handing something over — compare and swap

**Use for every ownership change**: rehoming a creature (M3), and anything later
that moves a thing from one player to another.

**The rule:** the `UPDATE` states the condition it expects, and you check whether it
actually changed a row.

```sql
UPDATE creatures
   SET owner_id = :new_owner, pound_status = 'adopted'
 WHERE id = :creature_id
   AND owner_id = :expected_current_owner   -- the state we believe we saw
   AND pound_status = 'available'
```

Then: **if it changed no rows, somebody got there first.** Do not proceed. Tell the
player kindly that the creature has already been adopted.

**Why not check first, then update?** Because between the check and the update
there is a gap, and two people adopting the last creature in the pound will both
pass the check. Putting the expectation *inside* the statement closes the gap — the
database decides, and exactly one request wins.

**Already used here:** `InventoryRepository::removeOne()`, which is what stops a
player being paid twice for one item.

---

## 2. Once a day — a ledger row with a UNIQUE

**Use for every once-per-day claim**: the daily click reward (M6.2), the free daily
plant click (M12.2), a daily chest.

**The rule:** write a row recording the claim, and let a UNIQUE constraint refuse
the second one.

```sql
INSERT IGNORE INTO user_daily_actions (user_id, action_key, action_date)
VALUES (:user, :action, :today)
```

If it inserted a row, the claim is theirs. If it inserted nothing, they have already
claimed today. **No reading first, no counting, no comparing timestamps.**

**The UNIQUE must exist in the Phinx migration**, not only in the code that writes
to it. A constraint that lives in the developer's head is not a constraint.
`ReportsUniquenessTest` checks the ones we have by querying the database's own
description of itself.

**Already used here:** the `reports` table — one report per person per thing.

**A deviation that resolved itself:** daily adoption used a rolling cooldown
(`users.last_adopted_at`) rather than a ledger, and this section used to record
that as a known difference. M2 retired adoption entirely — creatures are bought
with gems now — so the deviation is gone rather than fixed. There is currently no
once-per-calendar-day claim anywhere in the codebase; the first one built uses the
ledger above.

**The nearest thing we do have** is the gem earning cap
(`gameplay.currency.daily_cap`), and it is deliberately *not* a calendar day. It
counts paid pets in a **rolling** 24 hours
(`PettingRepository::countPaidPetsBy`), because a calendar cap resets at midnight
in one timezone and hands somebody a double allowance at the boundary. Nothing
about it needs to be "once per day" in the human sense — it is a ceiling on
farming, not a daily ration.

---

## 3. Money — never read it and then write it

**Use for every currency change.**

**The rule:** a balance is changed by a single conditional statement, never by
reading it, deciding in code, and writing it back.

```sql
UPDATE users
   SET currency_balance = currency_balance - :amount
 WHERE id = :id AND currency_balance >= :amount   -- can never go negative
```

If it changed no rows, they could not afford it.

**If a read genuinely must inform a later write** — a case we have so far avoided —
then the read is `SELECT ... FOR UPDATE` inside a transaction, so nobody can change
the row between the two statements.

**Why the conditional update is preferred where possible:** it is stronger *and*
simpler. `FOR UPDATE` protects a gap; this pattern has no gap to protect. Reach for
`FOR UPDATE` only when the shape of the problem genuinely needs the value first.

**Already used here:** `UserRepository::deductCurrency()`.

**And the order matters.** In a sale, the item is removed **before** the money is
paid. It reads more naturally the other way round, and the other way round is wrong:
if the removal then failed, somebody would have been paid for a thing they still
own.

---

## 4. Things that must always be true — tested and enforced

Some facts about the economy must never stop being true. Each one is both an
automated test **and**, where the database can express it, a constraint:

| Must always be true | Enforced by |
| --- | --- |
| No balance is ever negative | UNSIGNED column, plus the conditional update above |
| No quantity is ever negative | UNSIGNED column |
| No "ghost" rows of quantity zero | The row is deleted when it empties |
| Every price is zero or more | UNSIGNED column |
| Nothing sells for more than a shop charges | `ItemSellValueTest`, against the real data |

`EconomyInvariantsTest` checks each of these against the actual database rather than
against what the code intends, because those are different claims.

**The sell-value rule is content, not code.** No code is capable of breaking it —
what breaks it is somebody typing a generous number into the panel one evening. So
the test walks every item every shop actually offers and fails naming the item.

---

## 5. Tunable numbers live in exactly one place

**The rule:** cooldowns, thresholds, limits, formulas and rates live in
`config/config.php`. Nothing is defined twice.

The file has no side effects — reading it changes nothing — so a test can load it
and check things about it. `ConfigSingleSourceTest` fails if a value that belongs in
config has been hardcoded somewhere in `src/`.

**Why it matters more than it looks.** The second definition is always the one
somebody forgets. We have already had it once: the currency was configured as
"coins" while the selling code said "gems", so the same thing had two names
depending on which page you were on.

If JavaScript ever needs one of these numbers, it is handed down from the config —
never retyped. A formula that exists twice will drift, and the version players see
will be the wrong one.

---

## 6. Progress is worked out, never stored

**The rule:** a creature's level and life stage are calculated from what it has
done. They are not columns.

`GrowthCalculator` turns experience into a level and a stage. `creatures` has no
`level` column and must never grow one — `DerivedProgressionTest` fails if it does.

**Why.** Two stored numbers that are supposed to agree eventually do not. A creature
whose stored level says 4 and whose experience says 6 is a bug nobody can explain
and a support question nobody can answer. Calculating it means there is one source
of truth, and "make creatures level faster" stays a one-line change to config.
