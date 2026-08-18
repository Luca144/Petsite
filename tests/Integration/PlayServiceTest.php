<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureInteractions;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\PlayableGames;
use Felkyo\Creatures\PlayService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests playing a game with a creature.
 *
 * @package Felkyo\Tests\Integration
 *
 * TWO THINGS THIS FILE IS REALLY ABOUT.
 *
 * That losing is never a punishment. A wrong guess still cheers the creature up,
 * because somebody came and played with it either way — and there is no outcome
 * anywhere in this feature that leaves a creature worse off than before. If that
 * ever changes, testAWrongGuessStillCheersTheCreatureUp goes red.
 *
 * And that the cooldown limits the REWARD and never the GAME. A creature that
 * played a minute ago still plays, still enjoys it, and says so. "Come back later"
 * is not a sentence this site says.
 */
final class PlayServiceTest extends DatabaseTestCase
{
    private PlayService $play;
    private CreatureRepository $creatures;
    private CreatureInteractions $interactions;
    private int $ownerId;
    private int $strangerId;
    private int $creatureId;

    /** Deliberately one game with two choices, so a test can name the answer. */
    private const GAMES = [
        'which-paw' => [
            'name' => 'Which paw?',
            'prompt' => 'Which paw is {name} holding it in?',
            'choices' => ['left', 'right'],
            'won' => '{name} opens its paw and shows you.',
            'lost' => 'The other one. {name} thinks this is very funny.',
        ],
    ];

    private const CONFIG = [
        'cooldown_seconds' => 300,
        'happiness_on_win' => 8,
        'happiness_on_loss' => 3,
        'energy_per_play' => 8,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->creatures = new CreatureRepository($this->connection);
        $this->interactions = new CreatureInteractions($this->connection);
        $users = new UserRepository($this->connection);
        $this->play = new PlayService(
            $this->connection,
            $this->interactions,
            new PlayableGames(self::GAMES),
            $this->moodCalculator(),
            self::CONFIG
        );

        $species = new SpeciesRepository($this->connection);
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $this->strangerId = $users->create('stranger', 'stranger@example.com', 'hash')->id;
        $this->creatureId = $this->creatures->create($this->ownerId, $species->findStarters()[0]->id, 'Biscuit')->id;
    }

    private function creature(): \Felkyo\Creatures\Creature
    {
        return $this->creatures->findById($this->creatureId);
    }

    /**
     * Drop the mood so a gain has room to show. A creature born at 80 happiness
     * cannot gain 8, and a test that cannot tell "it worked" from "it was already
     * full" proves nothing.
     */
    private function makeCreatureNeedy(): void
    {
        $this->interactions->saveMood($this->creatureId, 40, 60);
    }

    // ---- Starting a round ----

    public function testARoundNamesAGameAndHidesAnAnswerInsideIt(): void
    {
        $round = $this->play->startRound();

        $this->assertSame('which-paw', $round['slug']);
        // Two choices, so the answer is 0 or 1 and nothing else.
        $this->assertGreaterThanOrEqual(0, $round['answer']);
        $this->assertLessThan(2, $round['answer']);
    }

    public function testTheAnswerIsNotAlwaysTheSame(): void
    {
        // A predictable answer turns the game into a reward button. Twenty rounds of
        // a two-choice game landing on one side is about a one-in-a-million fluke,
        // so this is a fair check that the roll is real.
        $seen = [];
        for ($round = 0; $round < 20; $round++) {
            $seen[$this->play->startRound()['answer']] = true;
        }

        $this->assertCount(2, $seen, 'The hidden answer never changed. The game would be predictable.');
    }

    // ---- Guessing right ----

    public function testARightGuessCheersTheCreatureUp(): void
    {
        $this->makeCreatureNeedy();

        $result = $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);

        $this->assertTrue($result->playedAndWon());
        $this->assertSame(48, $this->creature()->happiness);
    }

    public function testPlayingCostsALittleEnergy(): void
    {
        $this->makeCreatureNeedy();

        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);

        $this->assertSame(52, $this->creature()->energy);
    }

    public function testARightGuessSaysSo(): void
    {
        $message = $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 0, 0)->message();

        $this->assertStringContainsString('Biscuit opens its paw', $message);
    }

    // ---- Guessing wrong ----

    public function testAWrongGuessStillCheersTheCreatureUp(): void
    {
        // THE RULE THIS FEATURE IS BUILT AROUND. Losing is not a punishment:
        // somebody came and played with the creature, which is the thing that
        // mattered. Guessing right is a bonus on top of that, not the price of
        // entry. Do not "fix" this by making a loss worth nothing.
        $this->makeCreatureNeedy();

        $result = $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 0);

        $this->assertFalse($result->playedAndWon());
        $this->assertTrue($result->played());
        $this->assertSame(43, $this->creature()->happiness);
    }

    public function testAWrongGuessNeverMakesACreatureLessHappy(): void
    {
        $this->makeCreatureNeedy();
        $before = $this->creature()->happiness;

        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 0);

        $this->assertGreaterThan(
            $before,
            $this->creature()->happiness,
            'Losing a game made a creature less happy. Nothing in this game may do that.'
        );
    }

    public function testWinningIsWorthMoreThanLosing(): void
    {
        // Both are positive; the win is simply better. If these ever came out equal,
        // guessing would stop mattering and the game would be a button.
        $this->makeCreatureNeedy();
        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 0);
        $afterLoss = $this->creature()->happiness;

        $this->interactions->markPlayed($this->creatureId);
        $this->interactions->saveMood($this->creatureId, 40, 60);
        // Clear the cooldown that markPlayed just set, so the win is rewarded too.
        $this->connection->exec('UPDATE creatures SET last_played_at = NULL WHERE id = ' . $this->creatureId);
        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);
        $afterWin = $this->creature()->happiness;

        $this->assertGreaterThan($afterLoss, $afterWin);
    }

    // ---- The cooldown ----

    public function testASecondGameStraightAwayIsStillPlayedButNotRewarded(): void
    {
        // The cooldown limits the REWARD, never the game. Being told "come back
        // later" is not a sentence this site says.
        $this->makeCreatureNeedy();
        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);
        $afterFirst = $this->creature()->happiness;

        $result = $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);

        $this->assertTrue($result->played(), 'A creature on cooldown refused to play at all.');
        $this->assertSame($afterFirst, $this->creature()->happiness);
    }

    public function testACooldownGameStillSaysSomethingWarm(): void
    {
        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);

        $message = $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1)->message();

        // It says what happened AND why nothing moved, without refusing anybody.
        $this->assertStringContainsString('plenty of games', $message);
        $this->assertStringNotContainsStringIgnoringCase('cannot', $message);
        $this->assertStringNotContainsStringIgnoringCase('come back later', $message);
    }

    public function testTheRewardReturnsOnceTheCooldownHasPassed(): void
    {
        $this->makeCreatureNeedy();
        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);
        $afterFirst = $this->creature()->happiness;

        // Age the last game past the cooldown.
        $this->connection->exec(
            'UPDATE creatures SET last_played_at = NOW() - INTERVAL 1 HOUR WHERE id = ' . $this->creatureId
        );

        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);

        $this->assertGreaterThan($afterFirst, $this->creature()->happiness);
    }

    public function testAnUnrewardedGameDoesNotPushTheCooldownFurtherAway(): void
    {
        // Otherwise an enthusiastic player would keep resetting their own cooldown
        // and never be rewarded again — a five-minute pause becoming a permanent one.
        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);
        $firstStamp = $this->connection->query(
            'SELECT last_played_at FROM creatures WHERE id = ' . $this->creatureId
        )->fetchColumn();

        $this->play->judge($this->ownerId, $this->creature(), 'which-paw', 1, 1);
        $secondStamp = $this->connection->query(
            'SELECT last_played_at FROM creatures WHERE id = ' . $this->creatureId
        )->fetchColumn();

        $this->assertSame($firstStamp, $secondStamp);
    }

    // ---- What it refuses ----

    public function testYouCannotPlayWithSomebodyElsesCreature(): void
    {
        $this->makeCreatureNeedy();

        $result = $this->play->judge($this->strangerId, $this->creature(), 'which-paw', 1, 1);

        $this->assertFalse($result->played());
        $this->assertSame(40, $this->creature()->happiness);
    }

    public function testAGameThatNoLongerExistsEndsTheRoundKindly(): void
    {
        // Config changed while a round was open. Nobody's fault, and not worth an
        // error page — so nothing is applied and the message invites another go.
        $this->makeCreatureNeedy();

        $result = $this->play->judge($this->ownerId, $this->creature(), 'a-retired-game', 0, 0);

        $this->assertFalse($result->played());
        $this->assertStringContainsString('Start another', $result->message());
        $this->assertSame(40, $this->creature()->happiness);
    }
}
