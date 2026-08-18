<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Http\Controllers\SearchController;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\PlayerFinder;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Tests finding a player — and, mostly, tests that you cannot find everybody.
 *
 * @package Felkyo\Tests\Integration
 *
 * The threat here is not contact. There is nothing harmful you can do with a
 * player once you have found them on this site: no messaging, no private channel,
 * no words of your own. The threat is ENUMERATION — somebody scripting their way
 * to a list of everybody here, which is the first step of anything else.
 *
 * So most of these tests are about what search REFUSES to tell you.
 */
final class SearchControllerTest extends DatabaseTestCase
{
    private Router $router;
    private int $searcherId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('reports', 'inventory', 'pettings', 'creatures', 'users', 'rate_limit_hits');
        $_SESSION = [];

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $templates->registerFunction('csrf_field', static fn (): string => '');

        $users = new UserRepository($this->connection);

        $controller = new SearchController(
            $templates,
            new Session(cookieSecure: false),
            new PlayerFinder(
                new ProfileRepository($this->connection),
                new AvatarSet(['default' => ['name' => 'The wandering visitor', 'file' => 'default.png']]),
                new RateLimiter(new RateLimitRepository($this->connection)),
                ['minimum_length' => 2, 'result_limit' => 3],
                ['max_attempts' => 60, 'window_seconds' => 3600]
            ),
            2
        );

        $this->router = new Router();
        $this->router->get('/players', [$controller, 'show']);

        foreach (['mira', 'mirabel', 'miro', 'mint', 'rowan', 'wren'] as $name) {
            $users->create($name, $name . '@example.com', 'hash');
        }
        $this->searcherId = $users->create('searcher', 'searcher@example.com', 'hash')->id;
    }

    /**
     * The search page, with the query where a browser really puts it.
     *
     * NOTE THE LAST ARGUMENT. A search form uses GET, so "q" arrives in the
     * ADDRESS, not in posted form values — which is the fifth argument here, not
     * the third.
     *
     * This test originally passed the query as posted data and passed happily
     * while search was completely broken on the real site: the box came back
     * empty every time and nothing was ever found. It proved the controller
     * worked given input it would never actually receive. If you change this
     * helper, change it towards what a browser does, never towards what makes the
     * test go green.
     */
    private function search(string $query): Response
    {
        return $this->router->dispatch(
            new Request('GET', '/players', [], '127.0.0.1', ['q' => $query])
        );
    }

    public function testSearchRequiresLogin(): void
    {
        // Not because a name is secret — because an open search box is the
        // cheapest way to script through a playerbase, and requiring an account
        // puts a rate-limitable identity behind every query.
        $response = $this->search('mi');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/login', $response->header('Location'));
    }

    public function testItFindsPlayersByThePrefixOfTheirName(): void
    {
        $_SESSION['user_id'] = $this->searcherId;

        $body = $this->search('mir')->body();

        $this->assertStringContainsString('mira', $body);
        $this->assertStringContainsString('miro', $body);
    }

    public function testItDoesNotMatchTheMiddleOfAName(): void
    {
        // "contains" matching would turn a single common letter into a large
        // slice of the playerbase. A prefix only answers a question somebody
        // already half knows the answer to.
        $_SESSION['user_id'] = $this->searcherId;

        $body = $this->search('owa')->body();

        $this->assertStringNotContainsString('rowan', $body);
    }

    public function testASingleLetterIsRefused(): void
    {
        // One letter is not a search, it is a way of listing everybody whose name
        // begins with "m".
        $_SESSION['user_id'] = $this->searcherId;

        $body = $this->search('m')->body();

        $this->assertStringContainsString('at least 2 letters', $body);
        $this->assertStringNotContainsString('>mira<', $body);
    }

    public function testResultsAreCappedSoAWidePrefixCannotBeHarvested(): void
    {
        $_SESSION['user_id'] = $this->searcherId;

        // Four names begin with "mi"; the cap is three.
        $body = $this->search('mi')->body();

        $this->assertSame(3, substr_count($body, 'class="search-result"'));
    }

    public function testAPlayerWhoOptsOutIsNeverFound(): void
    {
        $_SESSION['user_id'] = $this->searcherId;
        $this->connection->exec("UPDATE users SET is_findable = 0 WHERE username = 'mira'");

        $body = $this->search('mira')->body();

        // Mira is gone from the results...
        $this->assertStringNotContainsString('/player/mira"', $body);
        // ...but Mirabel, who also matches "mira", is still there. The opt-out is
        // one player choosing, not a switch that empties the search.
        $this->assertStringContainsString('/player/mirabel"', $body);
    }

    public function testAnEmptyResultExplainsThatSomePeopleOptOut(): void
    {
        // Said plainly so nobody concludes the search is broken when the person
        // they are looking for simply chose not to be listed.
        $_SESSION['user_id'] = $this->searcherId;

        $body = $this->search('zzz')->body();

        $this->assertStringContainsString('choose not to be findable', $body);
    }

    public function testAnUnfindablePlayerStillHasAPageForThoseWhoKnowTheName(): void
    {
        // Opting out is an unlisted phone number, not a closed door. This pins
        // that down so nobody later "fixes" it into hiding the profile as well.
        $this->connection->exec("UPDATE users SET is_findable = 0 WHERE username = 'mira'");

        $profile = (new ProfileRepository($this->connection))->findByUsername('mira');

        $this->assertNotNull($profile);
        $this->assertFalse($profile->isFindable);
    }

    public function testWildcardCharactersAreTreatedAsOrdinaryLetters(): void
    {
        // Without escaping, "%" would match every name on the site — a search box
        // that lists the playerbase in one keystroke.
        $_SESSION['user_id'] = $this->searcherId;

        $body = $this->search('%')->body();

        $this->assertStringNotContainsString('class="search-result"', $body);
    }

    public function testUnderscoreIsAlsoNotAWildcard(): void
    {
        $_SESSION['user_id'] = $this->searcherId;

        $body = $this->search('__')->body();

        $this->assertStringNotContainsString('class="search-result"', $body);
    }

    public function testNoResultEverCarriesAnEmailAddress(): void
    {
        $_SESSION['user_id'] = $this->searcherId;

        $body = $this->search('mir')->body();

        $this->assertStringNotContainsString('@example.com', $body);
    }

    public function testThereIsNoWayToAskForPlayersByRecency(): void
    {
        // New accounts are the least familiar with the site and the most likely to
        // be young, so a list of recent arrivals is precisely the tool somebody
        // would want for finding them. This checks the repository offers no such
        // thing — the sort of guarantee that is easy to lose to a helpful addition.
        $methods = get_class_methods(ProfileRepository::class);

        foreach ($methods as $method) {
            $this->assertStringNotContainsStringIgnoringCase('recent', $method);
            $this->assertStringNotContainsStringIgnoringCase('newest', $method);
            $this->assertStringNotContainsStringIgnoringCase('all', $method);
        }
    }

    public function testAQueryPostedAsFormDataIsIgnored(): void
    {
        // The other side of the bug this file learned from. A search box lives in
        // the address; posted values are a different channel and are not the one
        // this page reads. If somebody ever "helpfully" makes the controller
        // accept both, this fails and asks them why.
        $_SESSION['user_id'] = $this->searcherId;

        $response = $this->router->dispatch(
            new Request('GET', '/players', ['q' => 'mira'], '127.0.0.1')
        );

        $this->assertStringNotContainsString('class="search-result"', $response->body());
    }

    public function testTooMuchSearchingIsSlowedDown(): void
    {
        $_SESSION['user_id'] = $this->searcherId;

        $limited = new SearchController(
            new Engine(dirname(__DIR__, 2) . '/templates'),
            new Session(cookieSecure: false),
            new PlayerFinder(
                new ProfileRepository($this->connection),
                new AvatarSet(['default' => ['name' => 'x', 'file' => 'default.png']]),
                new RateLimiter(new RateLimitRepository($this->connection)),
                ['minimum_length' => 2, 'result_limit' => 3],
                ['max_attempts' => 2, 'window_seconds' => 3600]
            ),
            2
        );

        $router = new Router();
        $router->get('/players', [$limited, 'show']);

        $router->dispatch(new Request('GET', '/players', [], '127.0.0.1', ['q' => 'mi']));
        $router->dispatch(new Request('GET', '/players', [], '127.0.0.1', ['q' => 'mo']));
        $third = $router->dispatch(new Request('GET', '/players', [], '127.0.0.1', ['q' => 'ro']));

        $this->assertStringContainsString('slow down', $third->body());
    }
}
