<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * The changes an INTERACTION makes to a creature: petting, feeding, playing.
 *
 * @package Felkyo\Creatures
 *
 * WHY THESE FIVE METHODS LIVE TOGETHER, AWAY FROM THE REST. They are the ones with
 * teeth. Every one of them is part of a sequence that reads a creature's state and
 * then writes it back, which is the pattern CLAUDE.md warns about — and they were
 * previously scattered among ordinary read queries in CreatureRepository, where the
 * rule about how to call them safely had to be repeated on each one and could be
 * missed by anybody adding a sixth.
 *
 * THE RULE, ONCE, FOR ALL OF THEM:
 *
 *     Anything that changes a mood must run inside a transaction, and that
 *     transaction must call lockForInteraction() FIRST.
 *
 * That is not a style preference. Petting, feeding and playing all work out a new
 * value from the current one — how much happiness has faded, whether a treat is
 * still in the satchel, whether the play cooldown has passed. Two copies of the
 * same request (a double-tapped button, an impatient refresh, somebody firing it
 * twice deliberately) can otherwise both read the old state and both act on it:
 * two payments for one pet, two effects from one treat, two rewards for one game.
 *
 * addExperience() is the exception and says so on itself — XP only grows and has no
 * ceiling, so it is safe without any of this.
 *
 * The three services that use this class — PettingService, FeedingService and
 * PlayService — each open the transaction and take the lock at the top. Read any
 * one of them for the shape.
 */
final class CreatureInteractions
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * Claim this creature for the rest of the current transaction, so that two
     * interactions landing at the same instant are dealt with one after the other.
     *
     * WHY THIS EXISTS. Interacting is a read-then-write: we ask "has this person
     * petted recently?", or "is the treat still there?", or "has the play cooldown
     * passed?" — and then, if the answer allows, we write. Two copies of the same
     * request can both read the permissive answer and both go on to write. That is
     * the duplication problem CLAUDE.md names, and it became worth exploiting the
     * moment gems started going to the person doing the petting.
     *
     * SELECT ... FOR UPDATE takes a lock on this creature's row that lasts until
     * the transaction ends. The second request simply waits at this line; by the
     * time it continues, the first one's write is committed and visible, so its own
     * check now correctly says no. No interaction is lost and none is paid twice.
     *
     * It must therefore be called INSIDE a transaction and BEFORE the check.
     * Called outside one, the lock is released immediately and protects nothing.
     *
     * (It was called lockForPetting when petting was the only thing that needed it.
     * Feeding and playing need exactly the same protection, and a name that says
     * "petting" made it look as though they were borrowing something.)
     */
    public function lockForInteraction(int $creatureId): void
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM creatures WHERE id = :id FOR UPDATE'
        );
        $statement->execute([':id' => $creatureId]);
        $statement->fetchAll();
    }

    /**
     * Write a creature's new mood, and stamp both readings as true from now.
     *
     * WHY THE VALUES ARE ABSOLUTE AND NOT "ADD THIS MUCH". Happiness used to be a
     * tally with no ceiling, so "happiness = happiness + 1" was both correct and
     * safe under any amount of concurrency. It is now a 0–100 reading that FADES,
     * so the new value depends on how much has faded since it was last written —
     * arithmetic the database cannot do on its own, and which MoodCalculator
     * already does properly.
     *
     * That makes this a read-then-write, which is safe here for exactly one
     * reason: every caller runs inside a transaction that has already taken this
     * creature's row with lockForInteraction(). Do not call it from anywhere that
     * has not.
     */
    public function saveMood(int $creatureId, int $happiness, int $energy): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures
                SET happiness = :happiness,
                    happiness_at = NOW(),
                    energy = :energy,
                    energy_at = NOW(),
                    last_interacted_at = NOW()
              WHERE id = :id'
        );
        $statement->execute([
            ':happiness' => $happiness,
            ':energy' => $energy,
            ':id' => $creatureId,
        ]);
    }

    /**
     * Add experience to a creature.
     *
     * THE ONE METHOD HERE THAT NEEDS NO LOCK, and it is worth knowing why rather
     * than copying the pattern blindly. XP is a different kind of number from mood:
     * it only ever grows and has no ceiling, so "xp = xp + :gain" is correct
     * however many pets land at the same instant. Reading it first would be work
     * for nothing, and folding it into saveMood() would have given it mood's much
     * stricter rules for no reason at all.
     */
    public function addExperience(int $creatureId, int $xpGain): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET xp = xp + :xp_gain WHERE id = :id'
        );
        $statement->execute([':xp_gain' => $xpGain, ':id' => $creatureId]);
    }

    /**
     * Has this creature had a game within the last $withinSeconds seconds?
     *
     * WHAT THIS GATES, AND WHAT IT DOES NOT. It decides whether a game earns the
     * creature a mood bonus. It never decides whether a game may be played — a
     * creature that played a minute ago still plays, still enjoys it, and says so.
     * The cooldown exists because playing cheers a creature up for free while a
     * treat costs gems; without it, playing would simply be a better treat and the
     * shop would be pointless.
     *
     * As in the rate limiter and the petting cooldown, the number of seconds is
     * cast to an integer and placed straight into the SQL — safe because it is a
     * value we control, and INTERVAL will not take a bound parameter.
     */
    public function playedRecently(int $creatureId, int $withinSeconds): bool
    {
        $withinSeconds = max(0, $withinSeconds);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM creatures
              WHERE id = :id
                AND last_played_at IS NOT NULL
                AND last_played_at >= NOW() - INTERVAL ' . $withinSeconds . ' SECOND'
        );
        $statement->execute([':id' => $creatureId]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Note that this creature has just had a game.
     *
     * Only called when a game actually earned its bonus, so that a creature on
     * cooldown does not keep pushing its own cooldown further away every time
     * somebody plays with it — which would turn a five-minute pause into a
     * permanent one for anybody enthusiastic.
     */
    public function markPlayed(int $creatureId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET last_played_at = NOW() WHERE id = :id'
        );
        $statement->execute([':id' => $creatureId]);
    }
}
