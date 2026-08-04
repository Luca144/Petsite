<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\CreatureController;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the creature page through the real path: router -> controller ->
 * repository -> database, including the "who can see it" access rules.
 *
 * @package Felkyo\Tests\Integration
 */
final class CreatureControllerTest extends DatabaseTestCase
{
    private Router $router;
    private int $ownerId;
    private int $publicCreatureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $session = new Session(cookieSecure: false);
        // The shared layout may render the log-out form, which needs this helper.
        $templates->registerFunction('csrf_field', static fn (): string => '');

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $creatures = new CreatureRepository($this->connection);
        $growth = new GrowthCalculator(
            $config['gameplay']['growth']['xp_per_level'],
            $config['gameplay']['growth']['stage_start_levels']
        );
        $profileBuilder = new CreatureProfileBuilder(
            $species, $users, $growth, new PettingRepository($this->connection)
        );

        $controller = new CreatureController($templates, $session, $creatures, $profileBuilder);

        $this->router = new Router();
        $this->router->get('/creature/{id}', [$controller, 'show']);

        // Seed an owner and one public creature (of the first starter species).
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $speciesId = $species->findStarters()[0]->id;
        $this->publicCreatureId = $creatures->create($this->ownerId, $speciesId, 'Biscuit')->id;
    }

    private function get(string $path): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request('GET', $path, [], '127.0.0.1'));
    }

    public function testAPublicCreaturePageIsShown(): void
    {
        $response = $this->get('/creature/' . $this->publicCreatureId);

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('Biscuit', $response->body());
        // A brand-new creature is a baby.
        $this->assertStringContainsString('baby', $response->body());
    }

    public function testTheImagePathUsesTheSpeciesSlugAndStage(): void
    {
        $response = $this->get('/creature/' . $this->publicCreatureId);

        // The first starter species is "foxlen"; a new creature is a "baby".
        $this->assertStringContainsString('/assets/creatures/foxlen/baby.gif', $response->body());
    }

    public function testTheHeartCelebrationShowsOnceThenIsCleared(): void
    {
        // Set the one-time celebration (as PetController does after a pet).
        $_SESSION['celebrate'] = 'pet';

        $first = $this->get('/creature/' . $this->publicCreatureId);
        $this->assertStringContainsString('creature__portrait--celebrate', $first->body());

        // Viewing again does not celebrate — the flag was consumed.
        $second = $this->get('/creature/' . $this->publicCreatureId);
        $this->assertStringNotContainsString('creature__portrait--celebrate', $second->body());
    }

    public function testAnUnknownCreatureGivesA404(): void
    {
        $response = $this->get('/creature/999999');

        $this->assertSame(404, $response->statusCode());
        $this->assertStringContainsString('find that creature', $response->body());
    }

    public function testAPrivateCreatureIsHiddenFromAGuest(): void
    {
        $privateId = $this->makePrivateCreature();

        $response = $this->get('/creature/' . $privateId);

        // A guest is told it does not exist (404), not that it is private.
        $this->assertSame(404, $response->statusCode());
    }

    public function testAPrivateCreatureIsVisibleToItsOwner(): void
    {
        $privateId = $this->makePrivateCreature();

        // Log in as the owner.
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->get('/creature/' . $privateId);

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('Secret', $response->body());
    }

    /** Insert a private (not public) creature owned by the test owner. */
    private function makePrivateCreature(): int
    {
        $speciesId = (new SpeciesRepository($this->connection))->findStarters()[0]->id;
        $statement = $this->connection->prepare(
            'INSERT INTO creatures (owner_id, species_id, name, is_public) VALUES (?, ?, ?, 0)'
        );
        $statement->execute([$this->ownerId, $speciesId, 'Secret']);

        return (int) $this->connection->lastInsertId();
    }
}
