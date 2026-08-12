<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * Lets one of your creatures say hello, instead of the site doing it.
 *
 * @package Felkyo\Creatures
 *
 * WHY THIS EXISTS: the header used to say "hi, wren" on every page, forever. It
 * is a perfectly polite thing to say and it adds nothing — the player knows their
 * own name, and a site greeting you by it is the voice of a form, not of a place.
 *
 * A creature saying something is the same space on the page doing real work. It
 * tells you the creatures are there when you are not looking at them, which is
 * most of what makes a pet site feel inhabited rather than administered.
 *
 * TWO DECISIONS WORTH KNOWING:
 *
 * THE LINE CHANGES BY DAY, NOT BY PAGE. Picking randomly on every request would
 * mean the greeting flickering as you clicked around, which reads as noise rather
 * than as somebody talking. Choosing from the date means it is the same all day —
 * your creature is in a mood today — and different tomorrow. It also means no
 * database column and nothing to store.
 *
 * WHO SPEAKS IS THE SPOTLIGHT CREATURE. The one the player chose to show off, or
 * their newest if they have not chosen. That makes the "featured" setting do
 * something visible on every page rather than only reordering a grid.
 *
 * THE LINES ARE CONTENT. They live in config, so the artist can rewrite every one
 * of them without a developer — and she should, because this is the site's voice
 * more than any other text on it.
 */
final class CreatureGreeting
{
    /**
     * @param array<int, string> $lines Said by a creature. "{name}" becomes its name.
     */
    public function __construct(private array $lines)
    {
    }

    /**
     * What this creature says today, or null if there is nothing to say.
     *
     * Returns null rather than an empty string when there is no creature, so the
     * template has a clear "show nothing at all" case instead of rendering an
     * empty speech bubble.
     */
    public function lineFor(?Creature $creature, string $today): ?string
    {
        if ($creature === null || $this->lines === []) {
            return null;
        }

        return str_replace('{name}', $creature->name, $this->lines[$this->pick($creature, $today)]);
    }

    /**
     * Which line, chosen from the creature and the date together.
     *
     * Both go into the choice so that two creatures do not say the same thing on
     * the same day — otherwise a player with four creatures would notice the
     * seam immediately.
     *
     * crc32 is used simply as a way of turning text into a number. Nothing here
     * is a security decision, so a fast, boring hash is the right one.
     */
    private function pick(Creature $creature, string $today): int
    {
        return abs((int) crc32($today . '-' . $creature->id)) % count($this->lines);
    }
}
