<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Controllers\BrowseController;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the public browse page, including that a guest can see it and that
 * private creatures are not listed.
 *
 * @package Felkyo\Tests\Integration
 */
final class BrowseControllerTest extends DatabaseTestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $templates->registerFunction('csrf_field', static fn (): string => '');

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

        $controller = new BrowseController($templates, $creatures, $profileBuilder, 12);
        $this->router = new Router();
        $this->router->get('/browse', [$controller, 'show']);

        // One public creature and one private one, owned by a user.
        $ownerId = $users->create('owner', 'owner@example.com', 'hash')->id;
        $speciesId = $species->findStarters()[0]->id;
        $creatures->create($ownerId, $speciesId, 'PublicPip');
        $this->connection->prepare(
            'INSERT INTO creatures (owner_id, species_id, name, is_public) VALUES (?, ?, ?, 0)'
        )->execute([$ownerId, $speciesId, 'HiddenHush']);
    }

    private function get(): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request('GET', '/browse', [], '127.0.0.1'));
    }

    public function testAGuestCanBrowseAndSeesPublicCreatures(): void
    {
        // No one is logged in ($_SESSION is empty).
        $response = $this->get();

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('PublicPip', $response->body());
    }

    public function testPrivateCreaturesAreNotListed(): void
    {
        $response = $this->get();

        $this->assertStringNotContainsString('HiddenHush', $response->body());
    }
}
