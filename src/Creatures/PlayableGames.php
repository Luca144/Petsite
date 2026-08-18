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
     * How many things there are to choose between in this game. Used to roll the
     * hidden answer, so it must come from the game itself and never from the page.
     */
    public function choiceCountOf(string $slug): int
    {
        return count($this->games[$slug]['choices'] ?? []);
    }

    /**
     * Everything a page needs to draw one round: the game's name, its prompt with
     * the creature's name filled in, and its choices.
     *
     * The ANSWER IS NOT HERE. A template that could see the answer is a template
     * one careless edit away from printing it.
     *
     * @return array{slug: string, name: string, prompt: string, choices: array<int, string>}
     */
    public function presentation(string $slug, string $creatureName): array
    {
        $game = $this->games[$slug];

        return [
            'slug' => $slug,
            'name' => $game['name'],
            'prompt' => $this->fillIn($game['prompt'], $creatureName),
            'choices' => array_values($game['choices']),
        ];
    }

    /**
     * What the creature says about how it went.
     */
    public function outcomeLine(string $slug, string $creatureName, bool $won): string
    {
        $game = $this->games[$slug];

        return $this->fillIn($won ? $game['won'] : $game['lost'], $creatureName);
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
