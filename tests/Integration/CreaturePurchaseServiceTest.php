<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreaturePurchaseService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests buying a creature — and mostly tests that gems cannot be spent twice.
 *
 * @package Felkyo\Tests\Integration
 *
 * This is the only place currency LEAVES the game, so it is the mirror of
 * PettingServiceTest (the only place it enters). Both sides of the economy need
 * to be safe against the same request arriving twice at once, and both are proved
 * to refuse rather than merely to allow (CLAUDE.md section 6).
 */
final class CreaturePurchaseServiceTest extends DatabaseTestCase
{
    private CreaturePurchaseService $shop;
    private CreatureRepository $creatures;
    private UserRepository $users;
    private SpeciesRepository $species;
    private int $userId;
    private int $starterSpeciesId;
    private int $starterPrice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->users = new UserRepository($this->connection);
        $this->species = new SpeciesRepository($this->connection);
        $this->shop = new CreaturePurchaseService(
            $this->connection,
            $this->species,
            $this->creatures,
            $this->users,
            ['Pip'],
            'gems'
        );

        $this->userId = $this->users->create('buyer', 'buyer@example.com', 'hash')->id;

        // The cheapest thing on offer, and what it costs. Read from the data
        // rather than written here, so retuning prices does not break these tests.
        $cheapest = $this->shop->forSale()[0];
        $this->starterSpeciesId = $cheapest->id;
        $this->starterPrice = $cheapest->gemPrice;
    }

    private function balance(): int
    {
        return $this->users->findById($this->userId)->currencyBalance;
    }

    private function giveGems(int $amount): void
    {
        $this->users->addCurrency($this->userId, $amount);
    }

    // ---- What is on offer ----

    public function testEverythingOnOfferHasAPrice(): void
    {
        // A creature priced at zero would be free, and free creatures make gems
        // pointless. This is the invariant that keeps the shop meaningful.
        foreach ($this->shop->forSale() as $species) {
            $this->assertGreaterThan(
                0,
                $species->gemPrice,
                $species->name . ' is for sale at no cost, so gems buy nothing.'
            );
        }
    }

    public function testTheCheapestIsListedFirst(): void
    {
        // Somebody arriving with few gems should see what they can afford, not
        // scroll past what they cannot.
        $prices = array_map(
            static fn ($species): int => $species->gemPrice,
            $this->shop->forSale()
        );
        $sorted = $prices;
        sort($sorted);

        $this->assertSame($sorted, $prices);
    }

    // ---- Buying ----

    public function testBuyingTakesTheGemsAndGivesTheCreature(): void
    {
        $this->giveGems($this->starterPrice);

        $result = $this->shop->buy($this->userId, $this->starterSpeciesId);

        $this->assertTrue($result->isSuccessful(), $result->message());
        $this->assertSame(0, $this->balance());
        $this->assertCount(1, $this->creatures->findByOwner($this->userId));
    }

    public function testTheNewCreatureIsTheSpeciesThatWasBought(): void
    {
        $this->giveGems($this->starterPrice);

        $result = $this->shop->buy($this->userId, $this->starterSpeciesId);

        $this->assertSame($this->starterSpeciesId, $result->creature()->speciesId);
    }

    public function testTheResultCarriesTheCreatureSoThePageCanGoStraightToIt(): void
    {
        // Meeting the new arrival is the point of having bought it. If the result
        // did not carry it, the player would land back in a shop.
        $this->giveGems($this->starterPrice);

        $result = $this->shop->buy($this->userId, $this->starterSpeciesId);

        $this->assertNotNull($result->creature());
        $this->assertStringContainsString($result->creature()->name, $result->message());
    }

    public function testANewlyBoughtCreatureStartsHappy(): void
    {
        $this->giveGems($this->starterPrice);

        $bought = $this->shop->buy($this->userId, $this->starterSpeciesId)->creature();

        $this->assertSame(
            $this->config['gameplay']['mood']['starting_happiness'],
            $this->creatures->findById($bought->id)->happiness
        );
    }

    public function testYouCanBuyMoreThanOne(): void
    {
        // There is deliberately no cap on how many creatures somebody may own —
        // gems are the limit, and they are a kind one. A rule saying "you have
        // enough creatures now" is not a sentence this site should say.
        $this->giveGems($this->starterPrice * 3);

        for ($bought = 0; $bought < 3; $bought++) {
            $this->assertTrue($this->shop->buy($this->userId, $this->starterSpeciesId)->isSuccessful());
        }

        $this->assertCount(3, $this->creatures->findByOwner($this->userId));
    }

    // ---- What it refuses ----

    public function testBuyingWithoutEnoughGemsIsRefusedAndChangesNothing(): void
    {
        $this->giveGems($this->starterPrice - 1);

        $result = $this->shop->buy($this->userId, $this->starterSpeciesId);

        $this->assertFalse($result->isSuccessful());
        // Nothing taken, nothing given — and, crucially, never negative.
        $this->assertSame($this->starterPrice - 1, $this->balance());
        $this->assertCount(0, $this->creatures->findByOwner($this->userId));
    }

    public function testTheRefusalNamesTheExactGapAndWhereGemsComeFrom(): void
    {
        // Golden rules 3 and 5: the exact number in plain words, and never a dead
        // end — somebody with an empty purse cannot guess how gems arrive.
        $this->giveGems($this->starterPrice - 1);

        $message = $this->shop->buy($this->userId, $this->starterSpeciesId)->message();

        $this->assertStringContainsString('You need 1 more gem ', $message);
        $this->assertStringContainsString("petting other players' creatures", $message);
    }

    public function testBuyingWithAnEmptyPurseIsRefused(): void
    {
        $result = $this->shop->buy($this->userId, $this->starterSpeciesId);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $this->balance());
    }

    public function testASpeciesThatIsNotForSaleCannotBeBought(): void
    {
        // Somebody could read a species id off any creature page. Being visible is
        // not the same as being for sale.
        //
        // THE SPECIES IS MADE HERE rather than found among the seeded ones. Every
        // seeded species happens to be for sale today, so a version of this test
        // that hunted for one skipped itself — and a permission boundary with a
        // test that quietly does not run is a boundary with no test at all. This
        // way it is checked whatever the seed data happens to contain.
        // Cleared first, so the test can run twice: the species table is not one
        // of the tables setUp() empties (other tests rely on the seeded species),
        // and the slug is unique.
        $this->connection->exec("DELETE FROM species WHERE slug = 'not-for-sale'");
        $this->connection->exec(
            "INSERT INTO species (slug, name, flavour_text, is_starter, is_adoptable, gem_price)
             VALUES ('not-for-sale', 'Reclusive Thing', 'Keeps to itself.', 0, 0, 10)"
        );
        $secretiveId = (int) $this->connection->lastInsertId();

        $this->giveGems(10_000);

        $result = $this->shop->buy($this->userId, $secretiveId);

        $this->assertFalse($result->isSuccessful());
        $this->assertCount(0, $this->creatures->findByOwner($this->userId));
        $this->assertSame(10_000, $this->balance());

        // Leave the seeded species as they were, so no other test inherits a
        // stray one (tests must be runnable in any order — CLAUDE.md section 7).
        $this->connection->exec("DELETE FROM species WHERE slug = 'not-for-sale'");
    }

    public function testASpeciesThatDoesNotExistCannotBeBought(): void
    {
        $this->giveGems(10_000);

        $this->assertFalse($this->shop->buy($this->userId, 999999)->isSuccessful());
        $this->assertSame(10_000, $this->balance());
    }

    // ---- The one that stops gems being spent twice ----

    public function testEnoughForOneBuysExactlyOne(): void
    {
        // Two copies of the same request — a double-tapped button, or somebody
        // firing it twice deliberately. The second must find the gems gone.
        //
        // The safety is in UserRepository::deductCurrency(), whose UPDATE carries
        // its own "and only if you can afford it" condition, so the check and the
        // spend cannot come apart. Read its comment before changing any of this.
        $this->giveGems($this->starterPrice);

        $first = $this->shop->buy($this->userId, $this->starterSpeciesId);
        $second = $this->shop->buy($this->userId, $this->starterSpeciesId);

        $this->assertTrue($first->isSuccessful());
        $this->assertFalse(
            $second->isSuccessful(),
            'One payment bought two creatures — gems have been spent out of nothing.'
        );

        $this->assertCount(1, $this->creatures->findByOwner($this->userId));
        $this->assertSame(0, $this->balance());
    }

    public function testABalanceCanNeverGoNegative(): void
    {
        // The invariant behind the whole economy. Twenty attempts on one payment's
        // worth of gems must leave the purse at zero, never below it.
        $this->giveGems($this->starterPrice);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->shop->buy($this->userId, $this->starterSpeciesId);
        }

        $this->assertSame(0, $this->balance());
        $this->assertCount(1, $this->creatures->findByOwner($this->userId));
    }
}
