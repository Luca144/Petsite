<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Admin\SiteStatsService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Exploration\ExplorationRepository;
use Felkyo\Http\Request;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;

/**
 * The stats page: right numbers, and nothing about any one player.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.9 plan's two promises: a fixture world produces the counts, and
 * the privacy rule — aggregate only — is pinned by asserting a player's
 * name never reaches the page.
 */
final class StatsTest extends DatabaseTestCase
{
    private AdminWorld $world;
    private User $artist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'exploration_visits', 'guestbook_entries',
            'admin_recovery_codes', 'admin_second_factors', 'admin_audit_log',
            'user_roles', 'pettings', 'creatures', 'users'
        );
        $_SESSION = [];
        $this->world = AdminWorld::build($this->connection);
        $this->artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($this->artist);
        $this->world->passDoorFor($this->artist);
    }

    protected function tearDown(): void
    {
        $this->world->cleanUpArtFolder();
        parent::tearDown();
    }

    /** A small world: one wanderer, one creature, one pet, one search. */
    private function makeAWeekOfLife(): void
    {
        $users = new UserRepository($this->connection);
        $wandererId = $users->create('wanderer-test', 'wanderer@example.com', 'hash')->id;
        $species = (new SpeciesRepository($this->connection))->findStarters()[0];
        $creatureId = (new CreatureRepository($this->connection))
            ->create($wandererId, $species->id, 'Statling')->id;
        (new PettingRepository($this->connection))->record($creatureId, $this->artist->id);
        $exploration = new ExplorationRepository($this->connection);
        $exploration->startFreshWindow($wandererId, 'whispering-wood');
        $exploration->recordClick($wandererId, 'whispering-wood');
    }

    public function testTheNumbersAddUp(): void
    {
        $this->makeAWeekOfLife();

        $stats = (new SiteStatsService($this->connection))->gather();

        $this->assertGreaterThanOrEqual(2, $stats['players']['total'], 'the artist and the wanderer at least');
        $this->assertGreaterThanOrEqual(1, $stats['players']['newThisWeek']);
        $this->assertGreaterThanOrEqual(1, $stats['creatures']['total']);
        $this->assertGreaterThanOrEqual(1, $stats['care']['petsThisWeek']);

        // The adoption picture includes the species that was picked...
        $bySpecies = array_column($stats['bySpecies'], 'count', 'name');
        $this->assertGreaterThanOrEqual(1, $bySpecies[$this->starterName()] ?? 0);

        // ...and the wood counts its wanderer's window.
        $areas = array_column($stats['areas'], null, 'slug');
        $this->assertArrayHasKey('whispering-wood', $areas);
        $this->assertGreaterThanOrEqual(1, $areas['whispering-wood']['wanderers']);
        $this->assertGreaterThanOrEqual(1, $areas['whispering-wood']['searches']);
    }

    public function testThePageNamesContentButNeverAPlayer(): void
    {
        $this->makeAWeekOfLife();

        $body = $this->world->router
            ->dispatch(new Request('GET', '/admin/stats', [], '127.0.0.1'))->body();

        $this->assertStringContainsString('creatures in the world', $body);
        $this->assertStringContainsString('The Whispering Wood', $body);
        // The privacy pin: aggregate only means no player's name, ever.
        // (The logged-in artist's own name appears in the sidebar like on
        // every page — the WANDERER is the player the stats must not leak.)
        $this->assertStringNotContainsString('wanderer-test', $body);
        $this->assertStringNotContainsString('Statling', $body,
            'creature names belong to players too');
    }

    private function starterName(): string
    {
        return (new SpeciesRepository($this->connection))->findStarters()[0]->name;
    }
}
