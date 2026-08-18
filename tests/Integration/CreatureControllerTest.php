<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Guestbook\GuestbookMessages;
use Felkyo\Guestbook\GuestbookPanel;
use Felkyo\Guestbook\GuestbookRepository;
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
    /** Kept so a test can share data with the layout the way index.php does. */
    private Engine $templates;
    private int $ownerId;
    private int $publicCreatureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('guestbook_entries', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        // Kept on the test as well, so a test can share data with the layout the
        // way public/index.php does — which is how the celebration flag arrives.
        $this->templates = $templates;
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
            $species, $users, $growth, new PettingRepository($this->connection), $this->moodCalculator()
        );

        // The creature page also shows the guestbook, so the controller needs the
        // panel that gathers it. A small stand-in catalogue keeps these tests from
        // breaking whenever the real messages in config are reworded.
        $guestbookPanel = new GuestbookPanel(
            new GuestbookRepository($this->connection),
            new GuestbookMessages(['lovely-creature' => 'What a lovely creature.']),
            $config['gameplay']['guestbook']['entries_shown']
        );

        $controller = new CreatureController(
            $templates, $session, $creatures, $profileBuilder, $guestbookPanel,
            new InventoryRepository($this->connection),
            $config['gameplay']['creature_name_max_length']
        );

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

    /**
     * The heart plays when the page is told something just happened.
     *
     * WHY THIS TEST CHANGED SHAPE. It used to set $_SESSION['celebrate'] and expect
     * this controller to consume it, because the controller did. It no longer does:
     * the flag is read once in public/index.php and handed to every template, so
     * that the keepsake card in the sidebar can celebrate too — pressing pet or
     * feed on that card used to change a bar's width silently, which was the one
     * action on the site with no visible answer.
     *
     * The consumption itself is Session's job and is tested there (take-once). What
     * belongs HERE is that the page draws the celebration when it is told to, and
     * does not when it is not — so the flag is handed in the way the front
     * controller hands it, through shared template data.
     */
    public function testTheHeartCelebrationIsDrawnWhenThePageIsToldToCelebrate(): void
    {
        $this->templates->addData(['justPetted' => true]);

        $this->assertStringContainsString(
            'creature__portrait--celebrate',
            $this->get('/creature/' . $this->publicCreatureId)->body()
        );
    }

    public function testAnOrdinaryVisitDoesNotCelebrate(): void
    {
        // Nothing shared, nothing to celebrate. This is the ordinary case, and it
        // being quiet is what stops the heart from playing on every page view.
        $this->assertStringNotContainsString(
            'creature__portrait--celebrate',
            $this->get('/creature/' . $this->publicCreatureId)->body()
        );
    }

    /**
     * A guest can read a guestbook but is invited to log in rather than shown the
     * form — signing needs an identity to keep "one entry per person" meaningful.
     */
    public function testAGuestSeesTheGuestbookButIsAskedToLogInToSign(): void
    {
        $body = $this->get('/creature/' . $this->publicCreatureId)->body();

        $this->assertStringContainsString('Guestbook', $body);
        $this->assertStringNotContainsString('name="message_key"', $body);
    }

    public function testALoggedInVisitorIsOfferedTheMessageChooser(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $body = $this->get('/creature/' . $this->publicCreatureId)->body();

        $this->assertStringContainsString('name="message_key"', $body);
        $this->assertStringContainsString('What a lovely creature.', $body);
    }

    /**
     * Enforces CLAUDE.md section 8 for the guestbook specifically: the message
     * chooser must be radio buttons, never a dropdown. If someone later "tidies"
     * the eight cards into a <select>, this test fails and says why.
     */
    public function testTheMessageChooserIsRadioButtonsAndNotADropdown(): void
    {
        $_SESSION['user_id'] = $this->ownerId;

        $body = $this->get('/creature/' . $this->publicCreatureId)->body();

        $this->assertStringContainsString('type="radio"', $body);
        $this->assertStringNotContainsString(
            '<select',
            $body,
            'Dropdown menus are forbidden by CLAUDE.md section 8 — the guestbook '
            . 'chooser must stay radio-style cards.'
        );
    }

    /**
     * The "Remove" button on a guestbook entry belongs to the creature's OWNER
     * alone. A visitor — even the one who wrote the entry — must not be offered it.
     * (The controller refuses the action regardless; this is about not showing a
     * control that would only lead to a refusal.)
     */
    public function testOnlyTheOwnerIsShownTheRemoveButtonOnGuestbookEntries(): void
    {
        $this->addGuestbookEntry();

        // A stranger looking at the page.
        $_SESSION['user_id'] = $this->makeStranger();
        $this->assertStringNotContainsString(
            'guestbook-entry__remove',
            $this->get('/creature/' . $this->publicCreatureId)->body()
        );

        // The owner looking at the same page.
        $_SESSION['user_id'] = $this->ownerId;
        $this->assertStringContainsString(
            'guestbook-entry__remove',
            $this->get('/creature/' . $this->publicCreatureId)->body()
        );
    }

    /** Put one guestbook entry on the public creature, signed by a new visitor. */
    private function addGuestbookEntry(): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO guestbook_entries (creature_id, author_user_id, message_key)
             VALUES (?, ?, ?)'
        );
        $statement->execute([$this->publicCreatureId, $this->makeStranger(), 'lovely-creature']);
    }

    /** A second user who owns nothing here. Created once, then reused. */
    private function makeStranger(): int
    {
        $users = new UserRepository($this->connection);
        $existing = $users->findByUsername('stranger');

        return $existing !== null
            ? $existing->id
            : $users->create('stranger', 'stranger@example.com', 'hash')->id;
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
