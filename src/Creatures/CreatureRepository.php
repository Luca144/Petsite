<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * Reads and writes creatures in the database.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the only place that runs SQL for the creatures table. Even though
 * Core gives a player just one creature, the queries are built for the real
 * one-to-many relationship (a user can own MANY creatures) from the start — so
 * later increments (the collection view, adoption) need no rework.
 */
final class CreatureRepository
{
    /**
     * The columns every creature query fetches. Listed one by one, never SELECT *
     * (CLAUDE.md section 5).
     *
     * THE LAST TWO ARE NOT COLUMNS, THEY ARE ARITHMETIC, and doing it here is
     * deliberate. A creature's happiness and energy are readings taken at a
     * moment; how long ago that moment was is what turns them into how the
     * creature feels NOW. Asking the database for that difference means the same
     * clock that wrote the timestamp measures the gap. Working it out in PHP would
     * mean trusting the web server's clock and timezone to match the database's —
     * which they do, until the day somebody moves one of them.
     *
     * COALESCE to created_at covers a creature made before the mood columns
     * existed: its readings are as old as the creature itself, which is true.
     */
    private const COLUMNS = 'id, owner_id, species_id, name, xp, happiness, energy,
             bio, bio_hidden_at, is_public, featured_order, last_interacted_at, created_at,
             TIMESTAMPDIFF(SECOND, COALESCE(happiness_at, created_at), NOW()) AS happiness_age_seconds,
             TIMESTAMPDIFF(SECOND, COALESCE(energy_at, created_at), NOW()) AS energy_age_seconds';

    public function __construct(private PDO $connection)
    {
    }

    /**
     * Create a new creature owned by a user, and return the saved record.
     * XP, happiness and public-visibility use their database defaults (0, 0, and
     * public), so a fresh creature starts as a public baby with no interactions.
     */
    public function create(int $ownerId, int $speciesId, string $name): Creature
    {
        $statement = $this->connection->prepare(
            'INSERT INTO creatures (owner_id, species_id, name)
             VALUES (:owner_id, :species_id, :name)'
        );
        $statement->execute([
            ':owner_id' => $ownerId,
            ':species_id' => $speciesId,
            ':name' => $name,
        ]);

        $newCreature = $this->findById((int) $this->connection->lastInsertId());

        if ($newCreature === null) {
            throw new \RuntimeException('Failed to load the creature that was just created.');
        }

        return $newCreature;
    }

    /**
     * Find one creature by its id, or return null if there is no such creature.
     */
    public function findById(int $id): ?Creature
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creatures WHERE id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        $row = $statement->fetch();

        return is_array($row) ? Creature::fromRow($row) : null;
    }

    /**
     * Get all creatures owned by a user, newest first. Used to list a player's
     * own creatures (their home page now; the full collection view in B.3).
     *
     * @return Creature[]
     */
    public function findByOwner(int $ownerId): array
    {
        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creatures WHERE owner_id = :owner_id ORDER BY created_at DESC, id DESC'
        );
        $statement->execute([':owner_id' => $ownerId]);

        return array_map(
            static fn (array $row): Creature => Creature::fromRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * Get the most recently created PUBLIC creatures, across all users, for the
     * browse page. Private creatures are never included. The limit is cast to an
     * integer and placed straight into the SQL (safe: it is our own value; MySQL
     * will not accept a bound parameter for LIMIT under real prepared statements).
     *
     * @return Creature[]
     */
    public function findRecentPublic(int $limit): array
    {
        $limit = max(1, $limit);

        $statement = $this->connection->query(
            'SELECT ' . self::COLUMNS . ' FROM creatures
              WHERE is_public = 1
              ORDER BY created_at DESC, id DESC
              LIMIT ' . $limit
        );

        return array_map(
            static fn (array $row): Creature => Creature::fromRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * Save a creature's bio (its owner-written description).
     *
     * NOTE THE OWNER IN THE WHERE CLAUSE. It is there on purpose, and it is not
     * redundant with the check the controller already does. This is the rule the
     * owned-thing model sets for everything a player owns (docs/owned-things.md):
     * the query itself says who is allowed, so that if the check further up were
     * ever removed, moved, or forgotten during a rewrite, this statement would
     * simply match no rows and change nothing. A permission check you can delete
     * by accident is not really a permission check.
     *
     * Pass the id of the person DOING the edit — not the creature's stored owner
     * id, which would make the condition always true and the protection fake.
     */
    public function updateBio(int $creatureId, int $editorUserId, string $bio): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET bio = :bio WHERE id = :id AND owner_id = :editor_user_id'
        );
        $statement->execute([
            ':bio' => $bio,
            ':id' => $creatureId,
            ':editor_user_id' => $editorUserId,
        ]);
    }

    /**
     * Rename a creature (change its player-chosen name).
     *
     * Same ownership protection as updateBio: the owner_id must match, or nothing changes.
     * This prevents name changes on creatures the player doesn't own, even if a check
     * elsewhere is removed or bypassed.
     */
    public function updateName(int $creatureId, int $editorUserId, string $name): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET name = :name WHERE id = :id AND owner_id = :editor_user_id'
        );
        $statement->execute([
            ':name' => $name,
            ':id' => $creatureId,
            ':editor_user_id' => $editorUserId,
        ]);
    }

    /**
     * Claim this creature for the duration of the current transaction, so that two
     * pets landing at the same instant are dealt with one after the other.
     *
     * WHY THIS EXISTS. Petting is a read-then-write: we ask "has this person petted
     * this creature recently?" and then, if not, we write a petting row and pay
     * them. Two copies of the same request arriving together — a double-tapped
     * button, an impatient refresh, or somebody deliberately firing it twice — can
     * both read "no, not recently", and both go on to write and both get paid. That
     * is the currency-duplication problem CLAUDE.md names, and it became worth
     * exploiting the moment gems started going to the person doing the petting.
     *
     * SELECT ... FOR UPDATE takes a lock on this creature's row that lasts until the
     * transaction ends. The second request simply waits at this line, and by the
     * time it continues, the first one's petting row is committed and visible — so
     * its cooldown check now correctly says "yes, recently". No pet is lost and none
     * is paid for twice.
     *
     * It must therefore be called INSIDE a transaction and BEFORE the cooldown
     * check. Called outside one, the lock is released immediately and protects
     * nothing.
     */
    public function lockForPetting(int $creatureId): void
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
     * which is arithmetic the database cannot do on its own, and which
     * MoodCalculator already does properly.
     *
     * That makes this a read-then-write, and read-then-write is the pattern
     * CLAUDE.md warns about. It is safe here for one reason: the only caller runs
     * inside a transaction that has already taken this creature's row with
     * lockForPetting(). Do not call this from anywhere that has not.
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
     * Kept as its own method, and as a RELATIVE update, because XP is a different
     * kind of number from mood: it only ever grows and has no ceiling, so
     * "xp = xp + :gain" is safe however many pets land at the same instant — no
     * lock and no reading-first required. Mixing it into saveMood() would have
     * quietly given it mood's much stricter rules for no reason.
     */
    public function addExperience(int $creatureId, int $xpGain): void
    {
        $statement = $this->connection->prepare(
            'UPDATE creatures SET xp = xp + :xp_gain WHERE id = :id'
        );
        $statement->execute([':xp_gain' => $xpGain, ':id' => $creatureId]);
    }

    /**
     * The creatures to show on a player's profile — PUBLIC ones only.
     *
     * WHY PUBLIC ONLY, EVEN FOR THE OWNER LOOKING AT THEIR OWN PAGE: a profile is
     * the page other people see, so showing the owner something different from
     * what a visitor sees would defeat the point. The build plan asks that a
     * player be shown clearly what others can see, and the plainest way to do that
     * is for the page to BE what others see. The owner is told separately how many
     * of their creatures are hidden, so nothing feels lost.
     *
     * This is also the reason featuring a creature cannot expose a private one:
     * the filter is here, at the point of display, rather than in the code that
     * saves the choice. A creature made private later stops appearing on the page
     * with nobody having to remember to un-feature it.
     *
     * ORDER: featured ones first in the order the owner chose, then everything
     * else newest-first. So a page always has something on it — a player who has
     * never touched the featured setting still gets a sensible page.
     *
     * @return Creature[]
     */
    public function findForProfile(int $ownerId, int $limit): array
    {
        $limit = max(1, $limit);

        $statement = $this->connection->prepare(
            'SELECT ' . self::COLUMNS . ' FROM creatures
              WHERE owner_id = :owner_id AND is_public = 1
              ORDER BY featured_order IS NULL, featured_order ASC, created_at DESC, id DESC
              LIMIT ' . $limit
        );
        $statement->execute([':owner_id' => $ownerId]);

        return array_map(
            static fn (array $row): Creature => Creature::fromRow($row),
            $statement->fetchAll()
        );
    }

    /**
     * How many of this player's creatures are hidden from their public page.
     * Used to tell the owner plainly, rather than leaving them wondering where a
     * creature went.
     */
    public function countPrivateForOwner(int $ownerId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM creatures WHERE owner_id = :owner_id AND is_public = 0'
        );
        $statement->execute([':owner_id' => $ownerId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Which of this player's creatures are currently featured, in their chosen
     * order. Used to show the edit form what is already ticked.
     *
     * Deliberately not filtered by is_public: this is the owner looking at their
     * own settings, and a private creature they had featured should still show as
     * ticked rather than silently losing its place. It simply does not appear on
     * the public page — the filter for that lives in findForProfile().
     *
     * @return array<int, int>
     */
    public function findFeaturedIds(int $ownerId): array
    {
        $statement = $this->connection->prepare(
            'SELECT id FROM creatures
              WHERE owner_id = :owner_id AND featured_order IS NOT NULL
              ORDER BY featured_order ASC'
        );
        $statement->execute([':owner_id' => $ownerId]);

        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $statement->fetchAll()
        );
    }
}
