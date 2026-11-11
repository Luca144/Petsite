<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Exploration\AreaRepository;
use Felkyo\Exploration\ExplorationRepository;
use Felkyo\Exploration\ExplorationService;
use Felkyo\Exploration\ItemRewardGranter;
use Felkyo\Exploration\WeightedPicker;
use Felkyo\Http\Controllers\ExplorationController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests the exploration pages and search action through the router.
 *
 * @package Felkyo\Tests\Integration
 *
 * Areas are database content since M2.5, so the fixture creates a real
 * area row (uniquely-slugged per run — the seeded whispering-wood is
 * shared by the whole suite and never touched) and the controller reads
 * it through the real repository, exactly as the front controller wires it.
 */
final class ExplorationControllerTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private ExplorationService $exploration;
    private AreaRepository $areas;
    private int $userId;
    private string $areaSlug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'exploration_visits', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');

        \Felkyo\Texts\TextTemplateFunctions::register($templates, new \Felkyo\Texts\TextCatalogue());
        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $templates->registerFunction('csrf_field', function (): string {
            return '<input type="hidden" name="_csrf_token" value="'
                . htmlspecialchars($this->csrf->token(), ENT_QUOTES, 'UTF-8') . '">';
        });

        $this->exploration = new ExplorationService(
            new ExplorationRepository($this->connection),
            new WeightedPicker(),
            new CreatureRepository($this->connection),
            new SpeciesRepository($this->connection),
            new ItemRewardGranter($this->connection, new InventoryRepository($this->connection)),
            ['clicks_per_visit' => 2, 'window_seconds' => 3600, 'creature_names' => ['Pip']]
        );
        $rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));

        // A tiny published test area with a "nothing" loot table (so no
        // creatures are made). Unique slug per run — see the class note.
        $this->areas = new AreaRepository($this->connection);

        // Sweep away groves left by earlier tests and runs: the areas table
        // is never truncated (the seeded whispering-wood belongs to the whole
        // suite), so our uniquely-slugged fixtures would pile up - and a
        // still-published leftover would haunt the "draft areas are
        // invisible" listing check. Spots and loot go with them (CASCADE).
        $this->connection->exec("DELETE FROM areas WHERE slug LIKE 'grove-%'");
        $area = $this->areas->create('Test Grove', 'grove-' . substr(uniqid(), -8));
        $this->areas->replaceSpots($area->id, [['x' => 50, 'y' => 50]]);
        $this->areas->replaceLoot($area->id, [
            ['type' => 'nothing', 'item_id' => null, 'weight' => 1, 'message' => 'Nothing here.'],
        ]);
        $this->areas->setStatus($area->id, 'published');
        $this->areaSlug = $area->slug;

        $controller = new ExplorationController(
            $templates, $session, $this->csrf, $this->exploration, $rateLimiter,
            $this->areas, ['rate_limit' => $config['security']['rate_limit_explore']]
        );

        $this->router = new Router();
        $this->router->get('/explore', [$controller, 'index']);
        $this->router->get('/explore/{area}', [$controller, 'show']);
        $this->router->post('/explore/{area}', [$controller, 'search']);

        $this->userId = (new UserRepository($this->connection))
            ->create('explorer', 'explorer@example.com', 'hash')->id;
    }

    private function dispatch(string $method, string $path, array $post = []): \Felkyo\Http\Response
    {
        return $this->router->dispatch(new Request($method, $path, $post, '127.0.0.1'));
    }

    public function testTheAreaListRequiresLogin(): void
    {
        $response = $this->dispatch('GET', '/explore');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testAnAreaSceneIsShownWhenLoggedIn(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->dispatch('GET', '/explore/' . $this->areaSlug);

        $this->assertSame(200, $response->statusCode());
        $this->assertStringContainsString('Test Grove', $response->body());
        $this->assertStringContainsString('Searches left', $response->body());
    }

    public function testAnUnknownAreaRedirectsToTheList(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $response = $this->dispatch('GET', '/explore/nowhere');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/explore', $response->header('Location'));
    }

    public function testADraftAreaIsAsInvisibleAsAMadeUpOne(): void
    {
        $_SESSION['user_id'] = $this->userId;

        // Back to draft: both routes must refuse, and the list must not name it.
        $area = $this->areas->findBySlugPublished($this->areaSlug);
        $this->areas->setStatus($area->id, 'draft');

        $this->assertSame(302, $this->dispatch('GET', '/explore/' . $this->areaSlug)->statusCode());
        $this->assertSame(302, $this->dispatch('POST', '/explore/' . $this->areaSlug, [
            '_csrf_token' => $this->csrf->token(),
        ])->statusCode());
        $this->assertStringNotContainsString('Test Grove', $this->dispatch('GET', '/explore')->body());
    }

    public function testSearchingConsumesAClick(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $this->assertSame(2, $this->exploration->remainingClicks($this->userId, $this->areaSlug));

        $response = $this->dispatch('POST', '/explore/' . $this->areaSlug, ['_csrf_token' => $this->csrf->token()]);

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/explore/' . $this->areaSlug, $response->header('Location'));
        $this->assertSame(1, $this->exploration->remainingClicks($this->userId, $this->areaSlug));
    }

    public function testSearchingRequiresLogin(): void
    {
        $response = $this->dispatch('POST', '/explore/' . $this->areaSlug, ['_csrf_token' => $this->csrf->token()]);

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testSearchingWithoutAValidCsrfTokenConsumesNoClick(): void
    {
        $_SESSION['user_id'] = $this->userId;

        $this->dispatch('POST', '/explore/' . $this->areaSlug, ['_csrf_token' => 'wrong']);

        // Nothing was spent.
        $this->assertSame(2, $this->exploration->remainingClicks($this->userId, $this->areaSlug));
    }
}
