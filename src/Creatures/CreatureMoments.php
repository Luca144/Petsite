<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * Every few clicks, one of your creatures pops up and does something.
 *
 * @package Felkyo\Creatures
 *
 * WHY THIS EXISTS (and why it replaced the always-on header line): a creature's
 * line used to sit in the header of every page, in the same spot, changing once
 * a day. Anything that is always there becomes wallpaper — after three visits
 * nobody reads it. Made OCCASIONAL, the same line turns into a small found
 * moment: rarity is what makes it feel like the creature chose to appear.
 *
 * THREE DECISIONS WORTH KNOWING:
 *
 * NO REWARD IS ATTACHED. The moment is pure flavour. The instant it pays coins
 * or XP, players (and their alt accounts) would farm page loads for it — see
 * the alt-account-farming threat in CLAUDE.md section 6.
 *
 * ANY OWNED CREATURE CAN SPEAK, not only the featured one. A player with four
 * creatures should occasionally hear from the quiet ones; that is what makes a
 * flock feel like a flock rather than one mascot and three rows in a table.
 *
 * THE LINES AND THE CHANCE ARE CONTENT (config/config.php, under
 * gameplay.creature_moments). The artist can rewrite the site's voice and
 * retune how often it speaks without a developer.
 *
 * HOW RANDOMNESS IS HANDLED: the service normally uses PHP's random_int, but a
 * test can hand in its own number source through the constructor. That is what
 * makes "roll the dice" testable — the test scripts the dice.
 */
final class CreatureMoments
{
    /** @var callable(int, int): int */
    private $randomInt;

    /**
     * @param array<int, string> $lines Said by a creature. "{name}" becomes its name.
     * @param int $chancePercent How often a page shows a moment (0–100).
     * @param callable(int, int): int|null $randomInt Number source; tests inject one.
     */
    public function __construct(
        private array $lines,
        private int $chancePercent,
        ?callable $randomInt = null,
    ) {
        $this->randomInt = $randomInt ?? random_int(...);
    }

    /**
     * Roll for a moment. Returns null most of the time — that is the point.
     *
     * $summaries is the list from CreatureProfileBuilder::summariesFor(), so the
     * chosen entry already carries the species and growth stage the template
     * needs to draw the right portrait.
     *
     * @param  array<int, array{creature: Creature, species: ?Species, level: int, stage: string}> $summaries
     * @return array{summary: array{creature: Creature, species: ?Species, level: int, stage: string}, line: string}|null
     */
    public function maybeFor(array $summaries): ?array
    {
        // Nothing to say, or nobody to say it: no moment. Checked before the
        // roll so a configuration of 100% still cannot produce an empty bubble.
        if ($summaries === [] || $this->lines === [] || $this->chancePercent <= 0) {
            return null;
        }

        // The dice: a 20% chance means "1 to 100, show it on 20 or under".
        if (($this->randomInt)(1, 100) > $this->chancePercent) {
            return null;
        }

        // Both the creature AND the line are random. Varying only the line would
        // make the featured creature a wallpaper mascot all over again.
        $summary = $summaries[($this->randomInt)(0, count($summaries) - 1)];
        $line = $this->lines[($this->randomInt)(0, count($this->lines) - 1)];

        return [
            'summary' => $summary,
            'line' => str_replace('{name}', $summary['creature']->name, $line),
        ];
    }
}
