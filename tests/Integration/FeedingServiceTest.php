<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureInteractions;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\FeedingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests giving a creature a treat — and mostly tests what feeding REFUSES.
 *
 * @package Felkyo\Tests\Integration
 *
 * Feeding moves an owned thing and changes another owned thing, which is the
 * shape CLAUDE.md section 6 asks to be proved rather than asserted. So every
 * refusal in FeedingService has a test here: somebody else's creature, an item
 * you do not hold, something that is not food, and — the one that matters most —
 * one treat fed twice being consumed exactly once.
 *
 * The last of those is not hypothetical. A double-tapped button, an impatient
 * refresh, or somebody deliberately firing the request twice all produce it, and
 * an inventory that can be spent twice is currency made out of nothing.
 */
final class FeedingServiceTest extends DatabaseTestCase
{
    private FeedingService $feeding;
    private CreatureRepository $creatures;
    private CreatureInteractions $interactions;
    private InventoryRepository $inventory;
    private UserRepository $users;
    private SpeciesRepository $species;
    private int $ownerId;
    private int $strangerId;
    private int $creatureId;
    private int $speciesId;
    private int $honeyTreatId;
    private int $stickerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->interactions = new CreatureInteractions($this->connection);
        $this->inventory = new InventoryRepository($this->connection);
        $this->users = new UserRepository($this->connection);
        $this->species = new SpeciesRepository($this->connection);
        $this->feeding = new FeedingService(
            $this->connection,
            $this->interactions,
            $this->inventory,
            $this->moodCalculator(),
            $this->species
        );

        $this->ownerId = $this->users->create('owner', 'owner@example.com', 'hash')->id;
        $this->strangerId = $this->users->create('stranger', 'stranger@example.com', 'hash')->id;
        $this->speciesId = $this->species->findStarters()[0]->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $this->speciesId, 'Biscuit')->id;

        // Two seeded items: one that is food, one that plainly is not.
        $this->honeyTreatId = $this->itemIdBySlug('honey-treat');
        $this->stickerId = $this->itemIdBySlug('gold-star-sticker');

        // The tests below decide for themselves what this creature's species
        // thinks of a treat, so they start from a species with NO opinions —
        // otherwise every assertion about a plain treat would quietly depend on
        // whichever pairing the migration happened to seed.
        $this->setTastes(loves: null, dislikes: null);
    }

    /**
     * Set (or clear) what this creature's species adores and dislikes.
     *
     * Restored by setUp() on every test, since setUp() clears them again — so a
     * test that sets a taste cannot leak it into the next one.
     */
    private function setTastes(?int $loves, ?int $dislikes): void
    {
        $statement = $this->connection->prepare(
            'UPDATE species SET favourite_item_id = :loves, disliked_item_id = :dislikes WHERE id = :id'
        );
        $statement->execute([
            ':loves' => $loves,
            ':dislikes' => $dislikes,
            ':id' => $this->speciesId,
        ]);
    }

    private function itemIdBySlug(string $slug): int
    {
        $statement = $this->connection->prepare('SELECT id FROM items WHERE slug = :slug');
        $statement->execute([':slug' => $slug]);

        return (int) $statement->fetchColumn();
    }

    private function creature(): \Felkyo\Creatures\Creature
    {
        return $this->creatures->findById($this->creatureId);
    }

    /** How many of an item a user is holding right now. */
    private function heldBy(int $userId, int $itemId): int
    {
        return $this->inventory->findStackForUser($userId, $itemId)?->quantity ?? 0;
    }

    /**
     * Drop the creature's mood so a treat has room to work. A creature born at
     * 80 happiness cannot gain 10, and a test that could not tell the difference
     * between "the treat worked" and "it was already full" proves nothing.
     */
    private function makeCreatureNeedy(): void
    {
        $this->interactions->saveMood($this->creatureId, 40, 20);
    }

    // ---- The good case ----

    public function testATreatMakesTheCreatureHappierAndMoreRested(): void
    {
        $this->makeCreatureNeedy();
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $result = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        $this->assertTrue($result->isSuccessful(), $result->message());
        $fed = $this->creature();
        // Honey is +10 happiness and +20 energy (set by the migration).
        $this->assertSame(50, $fed->happiness);
        $this->assertSame(40, $fed->energy);
    }

    public function testTheTreatIsConsumed(): void
    {
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        $this->assertSame(0, $this->heldBy($this->ownerId, $this->honeyTreatId));
    }

    public function testTheMessageSaysWhatHappened(): void
    {
        // Golden rule 4: say what happened, in plain words, naming the thing.
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $message = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId)->message();

        $this->assertStringContainsString('Biscuit', $message);
        $this->assertStringContainsString('Honey Treat', $message);
    }

    public function testFeedingAnAlreadyHappyCreatureStillWorks(): void
    {
        // A creature at full happiness accepts a treat and says thank you; the
        // numbers simply do not move. Refusing would mean punishing somebody for
        // having looked after their creature well.
        $this->interactions->saveMood($this->creatureId, 100, 100);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $result = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(100, $this->creature()->happiness);
    }

    public function testHappinessNeverGoesPastFull(): void
    {
        $this->interactions->saveMood($this->creatureId, 95, 50);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        $this->assertSame(100, $this->creature()->happiness);
    }

    // ---- Tastes ----

    public function testAFavouriteTreatIsWorthMore(): void
    {
        $this->makeCreatureNeedy();
        $this->setTastes(loves: $this->honeyTreatId, dislikes: null);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        // Honey is +10 happiness; a favourite doubles it. 40 + 20.
        $this->assertSame(60, $this->creature()->happiness);
    }

    public function testAFavouriteTreatSaysSo(): void
    {
        // The numbers are small and nobody counts them. What somebody remembers is
        // that their creature ADORED the honey, so the message is the feature.
        $this->setTastes(loves: $this->honeyTreatId, dislikes: null);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $message = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId)->message();

        $this->assertStringContainsString('loves', $message);
        $this->assertStringContainsString('favourite', $message);
    }

    public function testADislikedTreatIsWorthLessButStillHelps(): void
    {
        $this->makeCreatureNeedy();
        $this->setTastes(loves: null, dislikes: $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        // Honey is +10; a quarter of that, rounded up, is 3. 40 + 3.
        $this->assertSame(43, $this->creature()->happiness);
    }

    public function testADislikedTreatIsNeverAPunishment(): void
    {
        // THE LINE THIS WHOLE FEATURE MUST NOT CROSS. A creature offered something
        // it does not care for eats it, cheers up a little, and pulls a face. It is
        // never made less happy, and the player is never worse off for having tried
        // to be kind. Golden rule 11: when in doubt, gentler.
        $this->makeCreatureNeedy();
        $before = $this->creature()->happiness;
        $this->setTastes(loves: null, dislikes: $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $result = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        $this->assertTrue($result->isSuccessful());
        $this->assertGreaterThan(
            $before,
            $this->creature()->happiness,
            'A disliked treat made a creature less happy. Nothing in this game may do that.'
        );
    }

    public function testADislikedTreatSaysSoWithoutBeingSad(): void
    {
        $this->setTastes(loves: null, dislikes: $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $message = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId)->message();

        // It happened, and it was funny rather than a telling-off.
        $this->assertStringContainsString('made a face', $message);
        foreach (['sorry', 'refused', 'wasted', 'sad', 'upset'] as $unkind) {
            $this->assertStringNotContainsStringIgnoringCase($unkind, $message);
        }
    }

    public function testTasteChangesHappinessButNotRest(): void
    {
        // Energy is what food does to a body: a chamomile bundle is just as restful
        // whether or not the creature enjoyed it. Taste is an opinion, not digestion.
        $this->interactions->saveMood($this->creatureId, 40, 20);
        $this->setTastes(loves: null, dislikes: $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        // Honey's +20 energy arrives in full despite the face. 20 + 20.
        $this->assertSame(40, $this->creature()->energy);
    }

    public function testACreatureWithNoOpinionsIsUnaffected(): void
    {
        $this->makeCreatureNeedy();
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        $this->assertSame(50, $this->creature()->happiness);
    }

    // ---- "Give them something nice" — the card's one-tap feed ----

    public function testTheFavouriteIsChosenEvenWhenItIsNotTheStrongest(): void
    {
        // "Give them something nice" means the thing they LIKE, not the biggest
        // number. Chamomile is the strongest treat by total effect; honey is the
        // favourite here, and the favourite wins.
        $this->setTastes(loves: $this->honeyTreatId, dislikes: null);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->itemIdBySlug('chamomile-bundle'));

        $best = $this->feeding->bestTreatFor($this->ownerId, $this->creature());

        $this->assertSame($this->honeyTreatId, $best->item->id);
    }

    public function testTheStrongestIsChosenWhenThereIsNoFavourite(): void
    {
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->itemIdBySlug('acorn-treat'));

        $best = $this->feeding->bestTreatFor($this->ownerId, $this->creature());

        // Honey (10 + 20) beats acorn (6 + 10).
        $this->assertSame($this->honeyTreatId, $best->item->id);
    }

    public function testADislikedTreatIsAvoidedWhenThereIsAnAlternative(): void
    {
        // Feeding a creature the one thing it pulls a face at, while something else
        // was in the bag, would be an odd reading of "give them a treat".
        $acornId = $this->itemIdBySlug('acorn-treat');
        $this->setTastes(loves: null, dislikes: $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $acornId);

        $best = $this->feeding->bestTreatFor($this->ownerId, $this->creature());

        $this->assertSame($acornId, $best->item->id);
    }

    public function testADislikedTreatIsStillChosenWhenItIsAllThereIs(): void
    {
        // Something is better than nothing, and a creature offered its least
        // favourite still cheers up. Refusing to feed at all would be worse.
        $this->setTastes(loves: null, dislikes: $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $best = $this->feeding->bestTreatFor($this->ownerId, $this->creature());

        $this->assertSame($this->honeyTreatId, $best->item->id);
    }

    public function testAnEmptySatchelHasNoBestTreat(): void
    {
        // Null, so the caller can say where treats come from rather than the button
        // silently doing nothing.
        $this->assertNull($this->feeding->bestTreatFor($this->ownerId, $this->creature()));
    }

    public function testSomethingThatIsNotFoodIsNeverChosen(): void
    {
        $this->inventory->addItem($this->ownerId, $this->stickerId);

        $this->assertNull($this->feeding->bestTreatFor($this->ownerId, $this->creature()));
    }

    // ---- What it refuses ----

    public function testYouCannotFeedSomebodyElsesCreature(): void
    {
        $this->inventory->addItem($this->strangerId, $this->honeyTreatId);
        $this->makeCreatureNeedy();

        $result = $this->feeding->feed($this->strangerId, $this->creature(), $this->honeyTreatId);

        $this->assertFalse($result->isSuccessful());
        // Nothing was taken and nothing was changed.
        $this->assertSame(1, $this->heldBy($this->strangerId, $this->honeyTreatId));
        $this->assertSame(40, $this->creature()->happiness);
    }

    public function testYouCannotFeedATreatYouDoNotHave(): void
    {
        $this->makeCreatureNeedy();

        $result = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(40, $this->creature()->happiness);
    }

    public function testARefusalSaysWhereTreatsComeFrom(): void
    {
        // Golden rule 3: never a dead end. Somebody with an empty satchel is told
        // where to get one, not merely that they have none.
        $message = $this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId)->message();

        $this->assertStringContainsString('store', $message);
    }

    public function testSomethingThatIsNotFoodIsRefusedAndNotConsumed(): void
    {
        $this->inventory->addItem($this->ownerId, $this->stickerId);
        $this->makeCreatureNeedy();

        $result = $this->feeding->feed($this->ownerId, $this->creature(), $this->stickerId);

        $this->assertFalse($result->isSuccessful());
        // The important half: a mistaken tap costs nothing.
        $this->assertSame(1, $this->heldBy($this->ownerId, $this->stickerId));
        $this->assertSame(40, $this->creature()->happiness);
    }

    public function testFeedingSomethingThatDoesNotExistIsRefused(): void
    {
        $result = $this->feeding->feed($this->ownerId, $this->creature(), 999999);

        $this->assertFalse($result->isSuccessful());
    }

    // ---- The one that stops treats being spent twice ----

    public function testOneTreatCannotBeFedTwice(): void
    {
        $this->makeCreatureNeedy();
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        // The creature object is loaded ONCE and used for both calls, exactly as
        // two copies of the same request would see it: both were built from the
        // same page, before either had happened.
        $creature = $this->creature();

        $first = $this->feeding->feed($this->ownerId, $creature, $this->honeyTreatId);
        $second = $this->feeding->feed($this->ownerId, $creature, $this->honeyTreatId);

        $this->assertTrue($first->isSuccessful());
        $this->assertFalse(
            $second->isSuccessful(),
            'One treat was fed twice — an item has been spent out of nothing.'
        );

        // And the effect landed exactly once: 40 + 10, not 40 + 20.
        $this->assertSame(50, $this->creature()->happiness);
    }

    public function testFeedingTwoOfTheSameTreatWorksTwice(): void
    {
        // The opposite guarantee: holding two really does mean feeding twice.
        // Without this, "cannot be fed twice" could be satisfied by a bug that
        // refuses the second feed regardless of what the player owns.
        $this->makeCreatureNeedy();
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);
        $this->inventory->addItem($this->ownerId, $this->honeyTreatId);

        $this->assertTrue($this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId)->isSuccessful());
        $this->assertTrue($this->feeding->feed($this->ownerId, $this->creature(), $this->honeyTreatId)->isSuccessful());

        $this->assertSame(0, $this->heldBy($this->ownerId, $this->honeyTreatId));
        $this->assertSame(60, $this->creature()->happiness);
    }
}
