<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The little guessing games you can play with a creature.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the catalogue. It reads the games out of config, picks one, and
 * knows how many choices each has. It holds no state and touches nothing — the
 * SECRET (which choice is the right one) is handed out by this class and kept by
 * the caller, because keeping it is a session concern and this class knows nothing
 * about HTTP.
 *
 * WHY THE GAMES ARE GUESSES AND NOT ARCADE GAMES. A game running in the browser
 * cannot be trusted to report its own result: whatever "I won" the page sends,
 * anybody can send. So a reward for winning would really be a reward for asking.
 * With the answer held on the server, a cheat would have to guess correctly —
 * which is playing. It also means the whole thing works as a form and three
 * buttons, with no JavaScript at all.
 *
 * THE DICE ARE PASSED IN, not rolled here, for the same reason WeightedPicker
 * takes its roll rather than making one: a test can hand it an exact number and
 * check exactly which game and which answer come out. The caller does the rolling.
 */
final class PlayableGames
{
    /**
     * @param array<string, array{
     *     name: string, prompt: string, choices: array<int, string>,
     *     won: string, lost: string
     * }> $games The "gameplay.play.games" section.
     */
    public function __construct(private array $games)
    {
    }

    /**
     * Every game's slug, in the order config lists them.
     *
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return array_keys($this->games);
    }

    /**
     * Is that the slug of a game that exists?
     *
     * The answer matters because a game slug travels through the session and comes
     * back with a submitted answer. Config can change between those two moments —
     * a game removed while somebody had a round open — and the honest response to
     * that is "this round is over", not a crash.
     */
    public function has(string $slug): bool
    {
        return isset($this->games[$slug]);
    }

    /**
     * Which game a roll lands on. The roll should be 0 .. slugCount() - 1; a roll
     * outside that is wrapped rather than refused, because a roll is our own number
     * and an off-by-one here should not be able to take the page down.
     */
    public function slugForRoll(int $roll): string
    {
        $slugs = $this->slugs();
        if ($slugs === []) {
            throw new \RuntimeException('No games are configured to play.');
        }

        return $slugs[abs($roll) % count($slugs)];
    }

    /**
     * Which KIND of game this is: 'guess' (one shot, pick one of a few) or
     * 'narrow' (several turns, told higher or lower each time).
     *
     * The two exist because three guessing games with different words are one game
     * wearing three hats. A hint game is a genuinely different thing to do: luck
     * against deduction. Anything unrecognised is treated as a guess, so a config
     * typo produces a playable game rather than a broken page.
     */
    public function kindOf(string $slug): string
    {
        $kind = $this->games[$slug]['kind'] ?? 'guess';

        return $kind === 'narrow' ? 'narrow' : 'guess';
    }

    /**
     * How many things there are to choose between in this game. Used to roll the
     * hidden answer, so it must come from the game itself and never from the page.
     *
     * A narrow game's "choices" are the numbers 1..range, so its count is the range.
     */
    public function choiceCountOf(string $slug): int
    {
        if ($this->kindOf($slug) === 'narrow') {
            return max(2, (int) ($this->games[$slug]['range'] ?? 20));
        }

        return count($this->games[$slug]['choices'] ?? []);
    }

    /**
     * How many tries a narrow game allows before it ends. One, for a guess game —
     * you pick and that is that.
     */
    public function triesOf(string $slug): int
    {
        if ($this->kindOf($slug) !== 'narrow') {
            return 1;
        }

        return max(1, (int) ($this->games[$slug]['tries'] ?? 4));
    }

    /**
     * The nudge after a wrong guess in a narrow game.
     *
     * IT SAYS ONLY A DIRECTION, never the number. That is what keeps the game
     * honest: the page learns "higher" and nothing else, so it can help the player
     * think without handing them the answer.
     */
    public function hintLine(string $slug, string $creatureName, bool $tooLow): string
    {
        $game = $this->games[$slug];
        $line = $tooLow ? ($game['higher'] ?? 'Higher.') : ($game['lower'] ?? 'Lower.');

        return $this->fillIn($line, $creatureName);
    }

    /**
     * Everything a page needs to draw one round: the game's name, its prompt with
     * the creature's name filled in, and its choices.
     *
     * The ANSWER IS NOT HERE. A template that could see the answer is a template
     * one careless edit away from printing it.
     *
     * A narrow game's choices are the numbers in its range, written out — so both
     * kinds render as the same thing on the page: a row of buttons, each its own
     * submit. Twenty small buttons is a number board, which is charming, needs no
     * JavaScript, and cannot be submitted blank.
     *
     * @return array{slug: string, kind: string, name: string, prompt: string, choices: array<int, string>}
     */
    public function presentation(string $slug, string $creatureName): array
    {
        $game = $this->games[$slug];
        $kind = $this->kindOf($slug);

        if ($kind === 'narrow') {
            $choices = [];
            for ($number = 1; $number <= $this->choiceCountOf($slug); $number++) {
                $choices[] = (string) $number;
            }
        } else {
            $choices = array_values($game['choices']);
        }

        return [
            'slug' => $slug,
            'kind' => $kind,
            'name' => $game['name'],
            'prompt' => $this->fillIn($game['prompt'], $creatureName),
            'choices' => $choices,
        ];
    }

    /**
     * What the creature says about how it went.
     *
     * A narrow game's losing line may name the answer with "{secret}" — revealing
     * it at the END is the satisfying part ("it was 13!"), and by then the round is
     * over so there is nothing left to give away.
     */
    public function outcomeLine(string $slug, string $creatureName, bool $won, ?int $secret = null): string
    {
        $game = $this->games[$slug];
        $line = $this->fillIn($won ? $game['won'] : $game['lost'], $creatureName);

        if ($secret !== null) {
            $line = str_replace('{secret}', (string) $secret, $line);
        }

        return $line;
    }

    /**
     * Put the creature's own name into a line of copy.
     *
     * The placeholder is what lets the lines live in config as content the Product
     * Owner can reword freely — and it is the creature's real name, so a line
     * always reads as being about the creature in front of you.
     */
    private function fillIn(string $line, string $creatureName): string
    {
        return str_replace('{name}', $creatureName, $line);
    }
}
