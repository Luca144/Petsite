<?php

declare(strict_types=1);

namespace Felkyo\Tests\Support;

use Felkyo\Creatures\Creature;

/**
 * Builds Creature objects for tests that need one without a database.
 *
 * @package Felkyo\Tests\Support
 *
 * WHY THIS EXISTS. Creature has a dozen constructor arguments, and a handful of
 * unit tests were building one positionally just to have "a creature called
 * Biscuit" to render. Every field added to Creature broke all of them at once, in
 * a way that says nothing about what the test was checking — adding the mood
 * readings in M2 was the third time it happened.
 *
 * A test should name only what it cares about. Everything else is a sensible,
 * boring default from here, and the next field added to Creature is one edit in
 * one file rather than a scavenger hunt.
 *
 * It is test support, not production code — the real creatures come from
 * CreatureRepository, and integration tests use that.
 */
final class Creatures
{
    /**
     * A creature with sensible defaults. Pass only what the test is about.
     *
     * The mood defaults describe a freshly hatched creature: happy, wide awake,
     * and both readings taken this instant (an age of 0 seconds), so nothing has
     * faded yet. A test that wants a neglected creature says so by passing an age.
     */
    public static function make(
        int $id = 1,
        string $name = 'Biscuit',
        int $ownerId = 1,
        int $xp = 0,
    ): Creature {
        return new Creature(
            id: $id,
            ownerId: $ownerId,
            speciesId: 1,
            name: $name,
            xp: $xp,
            happiness: 80,
            happinessAgeSeconds: 0,
            energy: 100,
            energyAgeSeconds: 0,
            bio: null,
            bioHiddenAt: null,
            isPublic: true,
            featuredOrder: null,
            lastInteractedAt: null,
            createdAt: null,
        );
    }

    /**
     * A creature whose mood readings are a given age, for testing how feelings
     * fade. Named arguments make the call site read as what it means:
     * Creatures::aged(happinessAgeSeconds: 86400 * 7).
     */
    public static function aged(
        int $happinessAgeSeconds = 0,
        int $energyAgeSeconds = 0,
        int $happiness = 80,
        int $energy = 100,
    ): Creature {
        return new Creature(
            id: 1,
            ownerId: 1,
            speciesId: 1,
            name: 'Biscuit',
            xp: 0,
            happiness: $happiness,
            happinessAgeSeconds: $happinessAgeSeconds,
            energy: $energy,
            energyAgeSeconds: $energyAgeSeconds,
            bio: null,
            bioHiddenAt: null,
            isPublic: true,
            featuredOrder: null,
            lastInteractedAt: null,
            createdAt: null,
        );
    }
}
