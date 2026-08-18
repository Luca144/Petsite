<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Creatures\PlayableGames;
use PHPUnit\Framework\TestCase;

/**
 * Tests the games catalogue — and mostly tests that it never hands out the answer.
 *
 * @package Felkyo\Tests\Unit
 *
 * The single thing holding these games up is that the right choice stays on the
 * server. Everything else about them is a form and three buttons. So the test that
 * matters here is testThePresentationNeverCarriesTheAnswer: a page that could see
 * the answer is a page one careless edit away from printing it, and then the reward
 * for winning becomes a reward for asking.
 */
final class PlayableGamesTest extends TestCase
{
    private const GAMES = [
        'hide-and-seek' => [
            'name' => 'Hide and seek',
            'prompt' => '{name} has hidden. Which one?',
            'choices' => ['the log', 'the fern', 'the hedge'],
            'won' => 'Found in one go! {name} is delighted.',
            'lost' => '{name} was behind another one.',
        ],
        'which-paw' => [
            'name' => 'Which paw?',
            'prompt' => 'Which paw is {name} holding it in?',
            'choices' => ['left', 'right'],
            'won' => 'Right first time.',
            'lost' => 'The other one. {name} finds this funny.',
        ],
    ];

    private function games(array $games = self::GAMES): PlayableGames
    {
        return new PlayableGames($games);
    }

    // ---- The answer never leaves ----

    public function testThePresentationNeverCarriesTheAnswer(): void
    {
        // THE TEST THIS FILE EXISTS FOR. Whatever else presentation() grows to
        // include, it must never include which choice is right — and "answer" must
        // not appear as a key under any name a template could reach.
        $shown = $this->games()->presentation('hide-and-seek', 'Biscuit');

        $this->assertArrayNotHasKey('answer', $shown);
        foreach (array_keys($shown) as $key) {
            $this->assertStringNotContainsStringIgnoringCase(
                'answer',
                (string) $key,
                'presentation() exposes something answer-shaped. The page must never see it.'
            );
        }
    }

    public function testThePresentationCarriesOnlyWhatAPageNeeds(): void
    {
        $shown = $this->games()->presentation('which-paw', 'Biscuit');

        $this->assertSame(['slug', 'name', 'prompt', 'choices'], array_keys($shown));
        $this->assertSame(['left', 'right'], $shown['choices']);
    }

    // ---- Copy ----

    public function testTheCreaturesNameIsPutIntoThePrompt(): void
    {
        $this->assertSame(
            'Biscuit has hidden. Which one?',
            $this->games()->presentation('hide-and-seek', 'Biscuit')['prompt']
        );
    }

    public function testTheCreaturesNameIsPutIntoTheOutcome(): void
    {
        $games = $this->games();

        $this->assertStringContainsString('Biscuit', $games->outcomeLine('hide-and-seek', 'Biscuit', true));
        $this->assertStringContainsString('Biscuit', $games->outcomeLine('hide-and-seek', 'Biscuit', false));
    }

    public function testALineWithNoPlaceholderIsLeftAlone(): void
    {
        // Not every line has to mention the creature by name, and one that does not
        // must not be mangled.
        $this->assertSame('Right first time.', $this->games()->outcomeLine('which-paw', 'Biscuit', true));
    }

    public function testLosingIsNeverWordedAsAFailure(): void
    {
        // Guessing wrong is not a failure — somebody came and played either way.
        // If a losing line ever starts telling somebody off, this catches it.
        $unkind = ['failed', 'wrong', 'lose', 'lost', 'sorry', 'bad luck', 'try harder'];

        foreach (array_keys(self::GAMES) as $slug) {
            $line = $this->games()->outcomeLine($slug, 'Biscuit', false);
            foreach ($unkind as $word) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $word,
                    $line,
                    'A losing line reads as a telling-off: "' . $line . '"'
                );
            }
        }
    }

    // ---- Picking ----

    public function testARollChoosesTheGameAtThatPosition(): void
    {
        $games = $this->games();

        $this->assertSame('hide-and-seek', $games->slugForRoll(0));
        $this->assertSame('which-paw', $games->slugForRoll(1));
    }

    public function testARollPastTheEndWrapsRatherThanBreaking(): void
    {
        // The roll is our own number, so an off-by-one is a bug in the caller — but
        // it must not be able to take down the page somebody is standing on.
        $games = $this->games();

        $this->assertSame('hide-and-seek', $games->slugForRoll(2));
        $this->assertSame('which-paw', $games->slugForRoll(-1));
    }

    public function testEveryConfiguredGameCanBeReached(): void
    {
        // A game nobody can ever be given is a game that does not exist. Rolling
        // across the whole range must produce all of them.
        $games = $this->games();
        $seen = [];

        for ($roll = 0; $roll < count(self::GAMES); $roll++) {
            $seen[$games->slugForRoll($roll)] = true;
        }

        $this->assertSame(array_keys(self::GAMES), array_keys($seen));
    }

    public function testNoGamesAtAllIsAnErrorRatherThanASilentNothing(): void
    {
        // Misconfiguration, and the loud version is the right one: a play button
        // that quietly does nothing is much harder to diagnose than an exception.
        $this->expectException(\RuntimeException::class);

        $this->games([])->slugForRoll(0);
    }

    // ---- Knowing what exists ----

    public function testTheChoiceCountComesFromTheGame(): void
    {
        // This is what the hidden answer is rolled against, so it must come from
        // the game itself and never from anything the page sends.
        $this->assertSame(3, $this->games()->choiceCountOf('hide-and-seek'));
        $this->assertSame(2, $this->games()->choiceCountOf('which-paw'));
    }

    public function testAGameThatWasRemovedIsSimplyNotThere(): void
    {
        // Config can change while somebody has a round open. The honest answer is
        // "that round is over", which needs this to return false rather than throw.
        $this->assertTrue($this->games()->has('which-paw'));
        $this->assertFalse($this->games()->has('a-game-that-was-retired'));
    }
}
