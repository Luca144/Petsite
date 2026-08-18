<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureInteractions;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for PettingService — the core interaction and cooldown, the XP it grants
 * toward growth, and the gems it pays the PERSON DOING THE PETTING.
 *
 * @package Felkyo\Tests\Integration
 *
 * THE PAYMENT TESTS ARE THE IMPORTANT ONES. Petting is the only way gems enter
 * the game, so every rule that limits it is a rule that keeps the economy from
 * being printed out of thin air. Each is proved to REFUSE, not only to allow
 * (CLAUDE.md section 6): your own creature does not pay, the cooldown does not
 * pay twice, and the daily cap stops paying once it is reached.
 */
final class PettingServiceTest extends DatabaseTestCase
{
    private PettingService $petting;
    private CreatureRepository $creatures;
    private CreatureInteractions $interactions;
    private PettingRepository $pettings;
    private UserRepository $users;
    private int $creatureId;
    private int $ownerId;
    private int $actorId;
    private int $otherActorId;

    // A small, explicit petting policy for these tests. The cap is deliberately
    // tiny (3 gems at 1 per pet) so a test can reach it in a few lines instead of
    // simulating a hundred pets.
    private const CONFIG = [
        'cooldown_seconds' => 30,
        'happiness_per_pet' => 5,
        'energy_per_pet' => 5,
        'xp_per_pet' => 20,
        'currency_per_pet' => 1,
        'currency_daily_cap' => 3,
        'currency_cap_window_seconds' => 86400,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->interactions = new CreatureInteractions($this->connection);
        $this->users = new UserRepository($this->connection);
        $this->pettings = new PettingRepository($this->connection);
        $this->petting = new PettingService(
            $this->connection,
            $this->pettings,
            $this->interactions,
            $this->users,
            $this->moodCalculator(),
            self::CONFIG,
            'gems'
        );

        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $this->users->create('owner', 'owner@example.com', 'hash')->id;
        $this->actorId = $this->users->create('actor', 'actor@example.com', 'hash')->id;
        $this->otherActorId = $this->users->create('other', 'other@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    private function balanceOf(int $userId): int
    {
        return $this->users->findById($userId)->currencyBalance;
    }

    private function creature(): \Felkyo\Creatures\Creature
    {
        return $this->creatures->findById($this->creatureId);
    }

    /**
     * What a creature's happiness should be after $pets pets, starting fresh.
     *
     * Written from config rather than as a literal, so retuning how much a pet is
     * worth does not turn every assertion below into a puzzle about where the
     * number came from. A creature is born at starting_happiness and each pet adds
     * happiness_per_pet, up to a ceiling of 100.
     */
    private function happinessAfter(int $pets): int
    {
        $start = $this->config['gameplay']['mood']['starting_happiness'];

        return min(100, $start + ($pets * self::CONFIG['happiness_per_pet']));
    }

    /**
     * Make every pet this person has done look like it happened an hour ago, so
     * the next one is past its cooldown. Used to test repeated petting without
     * making a test wait in real time.
     */
    private function ageAllPetsBy(int $actorUserId): void
    {
        $statement = $this->connection->prepare(
            'UPDATE pettings SET created_at = NOW() - INTERVAL 1 HOUR WHERE actor_user_id = :actor'
        );
        $statement->execute([':actor' => $actorUserId]);
    }

    /**
     * Create one more creature owned by somebody else, so a test can keep petting
     * past a single creature's cooldown.
     */
    private function anotherCreatureOwnedBySomeoneElse(string $name): \Felkyo\Creatures\Creature
    {
        $species = new SpeciesRepository($this->connection);

        return $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, $name);
    }

    public function testPettingRaisesHappinessAndGrantsXp(): void
    {
        $result = $this->petting->pet($this->actorId, $this->creature());

        $this->assertTrue($result->isSuccessful());
        $updated = $this->creature();
        $this->assertSame($this->happinessAfter(1), $updated->happiness);
        $this->assertSame(20, $updated->xp);
    }

    public function testPettingIsRejectedDuringCooldown(): void
    {
        $this->petting->pet($this->actorId, $this->creature());

        // The same person immediately petting again is refused...
        $second = $this->petting->pet($this->actorId, $this->creature());
        $this->assertFalse($second->isSuccessful());

        // ...and the stats did not change a second time.
        $updated = $this->creature();
        $this->assertSame($this->happinessAfter(1), $updated->happiness);
        $this->assertSame(20, $updated->xp);
    }

    public function testDifferentPeopleCanEachPetTheSameCreature(): void
    {
        // The cooldown is per person, so two different people both succeed.
        $this->assertTrue($this->petting->pet($this->actorId, $this->creature())->isSuccessful());
        $this->assertTrue($this->petting->pet($this->otherActorId, $this->creature())->isSuccessful());

        $updated = $this->creature();
        $this->assertSame($this->happinessAfter(2), $updated->happiness);
        $this->assertSame(40, $updated->xp);
    }

    public function testEnoughXpFromPettingChangesTheLifeStage(): void
    {
        // With 20 XP per pet, two pets (from two people) reach 40 XP, which is the
        // juvenile threshold (level 3) in the standard config.
        $this->petting->pet($this->actorId, $this->creature());
        $this->petting->pet($this->otherActorId, $this->creature());

        $growth = new GrowthCalculator(20, ['baby' => 1, 'juvenile' => 3, 'adult' => 6]);
        $this->assertSame('juvenile', $growth->stageFor($this->creature()->xp));
    }

    public function testPettingWorksAgainOnceTheCooldownHasPassed(): void
    {
        $this->petting->pet($this->actorId, $this->creature());
        $this->ageAllPetsBy($this->actorId);

        $again = $this->petting->pet($this->actorId, $this->creature());
        $this->assertTrue($again->isSuccessful());
        $this->assertSame(40, $this->creature()->xp);
    }

    // ---- Who gets paid ----

    public function testThePetterEarnsGemsNotTheOwner(): void
    {
        $this->assertSame(0, $this->balanceOf($this->actorId));
        $this->assertSame(0, $this->balanceOf($this->ownerId));

        $this->petting->pet($this->actorId, $this->creature());

        // The visitor is paid for visiting...
        $this->assertSame(1, $this->balanceOf($this->actorId));
        // ...and the owner is not paid for owning.
        $this->assertSame(0, $this->balanceOf($this->ownerId));
    }

    public function testTheSuccessMessageSaysWhatWasEarned(): void
    {
        // Golden rule 4: say what happened. A reward nobody is told about may as
        // well not exist.
        $result = $this->petting->pet($this->actorId, $this->creature());

        $this->assertStringContainsString('1 gem', $result->message());
    }

    public function testPettingYourOwnCreatureEarnsNothing(): void
    {
        // The first line of defence against alt-account farming: a creature you
        // own never pays you, however often you pet it.
        $this->petting->pet($this->ownerId, $this->creature());

        $this->assertSame(0, $this->balanceOf($this->ownerId));
        // It still counts as care — the creature is happier for it.
        $this->assertSame($this->happinessAfter(1), $this->creature()->happiness);
    }

    public function testPettingYourOwnCreatureSaysNothingAboutGems(): void
    {
        $result = $this->petting->pet($this->ownerId, $this->creature());

        $this->assertStringNotContainsString('gem', $result->message());
    }

    public function testTheCooldownStopsTheSameCreaturePayingTwice(): void
    {
        $this->petting->pet($this->actorId, $this->creature());
        // A second, immediate pet by the same person is on cooldown, so no more.
        $this->petting->pet($this->actorId, $this->creature());

        $this->assertSame(1, $this->balanceOf($this->actorId));
    }

    // ---- The daily cap ----

    public function testTheDailyCapStopsPayingOnceItIsReached(): void
    {
        // The cap in this test's config is 3 gems at 1 per pet. Pet four DIFFERENT
        // creatures so the per-creature cooldown never gets in the way — it is the
        // cap alone that must stop the fourth payment.
        $creatures = [
            $this->creature(),
            $this->anotherCreatureOwnedBySomeoneElse('Pip'),
            $this->anotherCreatureOwnedBySomeoneElse('Clover'),
            $this->anotherCreatureOwnedBySomeoneElse('Sage'),
        ];

        foreach ($creatures as $creature) {
            $this->petting->pet($this->actorId, $creature);
        }

        // Three paid, the fourth refused by the cap.
        $this->assertSame(3, $this->balanceOf($this->actorId));
    }

    public function testPettingStillWorksAfterTheCapIsReached(): void
    {
        // Reaching the cap must not break the game — the creature still gets its
        // happiness and XP, you simply stop being paid. Nothing is refused, and
        // nobody is punished (golden rules 10 and 11).
        $extra = $this->anotherCreatureOwnedBySomeoneElse('Pip');
        foreach ([$this->creature(), $extra] as $creature) {
            $this->petting->pet($this->actorId, $creature);
        }
        $overTheCap = $this->anotherCreatureOwnedBySomeoneElse('Clover');
        $this->petting->pet($this->actorId, $overTheCap);
        $capped = $this->anotherCreatureOwnedBySomeoneElse('Sage');

        $result = $this->petting->pet($this->actorId, $capped);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame($this->happinessAfter(1), $this->creatures->findById($capped->id)->happiness);
    }

    public function testTheCapIsPerPersonNotPerSite(): void
    {
        // One player hitting their cap must not stop anybody else earning.
        $creatures = [
            $this->creature(),
            $this->anotherCreatureOwnedBySomeoneElse('Pip'),
            $this->anotherCreatureOwnedBySomeoneElse('Clover'),
            $this->anotherCreatureOwnedBySomeoneElse('Sage'),
        ];
        foreach ($creatures as $creature) {
            $this->petting->pet($this->actorId, $creature);
        }
        $this->assertSame(3, $this->balanceOf($this->actorId));

        $this->petting->pet($this->otherActorId, $this->creature());

        $this->assertSame(1, $this->balanceOf($this->otherActorId));
    }

    public function testPetsOutsideTheWindowDoNotCountTowardTheCap(): void
    {
        // The cap is a rolling window, not a permanent ceiling. Yesterday's pets
        // must not keep somebody from earning today.
        $creatures = [
            $this->creature(),
            $this->anotherCreatureOwnedBySomeoneElse('Pip'),
            $this->anotherCreatureOwnedBySomeoneElse('Clover'),
        ];
        foreach ($creatures as $creature) {
            $this->petting->pet($this->actorId, $creature);
        }
        $this->assertSame(3, $this->balanceOf($this->actorId));

        // Push every one of those pets outside the 24-hour window.
        $statement = $this->connection->prepare(
            'UPDATE pettings SET created_at = NOW() - INTERVAL 2 DAY WHERE actor_user_id = :actor'
        );
        $statement->execute([':actor' => $this->actorId]);

        $this->petting->pet($this->actorId, $this->creature());

        $this->assertSame(4, $this->balanceOf($this->actorId));
    }
}
