<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\CollectionController;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the collection view through the router, including the login requirement.
 *
 * @package Felkyo\Tests\Integration
 */
final class CollectionControllerTest extends DatabaseTestCase
{
    private Router $router;
    private int $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $templates->registerFunction('csrf_field', static fn (): string => '');
        $session = new Session(cookieSecure: false);

        $users = new UserRepository($this->connection);
        $species = new SpeciesRepository($this->connection);
        $creatures = new CreatureRepository($this->connection);
        $growth = new GrowthCalculator(
            $config['gameplay']['growth']['xp_per_level'],
            $config['gameplay']['growth']['stage_start_levels']
        );
        $profileBuilder = new CreatureProfileBuilder(
            $species, $users, $growth, new PettingRepository($this->connection), $this->moodCalculator()
        );

        $controller = new CollectionController($templates, $session, $creatures, $profileBuilder);
        $this->router = new Router();
        $this->router->get('/creatures', [$controller, 'show']);

        // An owner with two creatures.
        $this->ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $speciesId = $species->findStarters()[0]->id;
        $creatures->create($this->ownerId, $speciesId, 'Biscuit');
        $creatures->create($this->ownerId, $speciesId, 'Clover');
    }

    private function get(): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request('GET', '/creatures', [], '127.0.0.1'));
    }

    public function testItListsTheLoggedInPlayersCreatures(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $response = $this->get();

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('Biscuit', $response->body());
        $this->assertStringContainsString('Clover', $response->body());
    }

    public function testAGuestIsSentToLogin(): void
    {
        $response = $this->get();

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testAPlayerWithNoCreaturesSeesAnEmptyState(): void
    {
        $lonelyUser = (new UserRepository($this->connection))
            ->create('lonely', 'lonely@example.com', 'hash');
        $_SESSION['user_id'] = $lonelyUser->id;

        $response = $this->get();

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('don', $response->body()); // "don't have any creatures"
    }
}
