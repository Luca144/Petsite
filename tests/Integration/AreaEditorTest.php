<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Exploration\Area;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Tests\Support\Pictures;
use Felkyo\Users\User;

/**
 * The area editor: what it builds, and everything it refuses.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.5 plan's promises as tests: publishing names its missing pieces;
 * a refused loot save keeps every typed character (once) and changes
 * nothing; spots off the scene are refused; a picture of the wrong kind is
 * refused; revert restores spots AND loot; and the seeded whispering-wood
 * still guards the items its table grants.
 *
 * FIXTURES: the seeded whispering-wood is migration data shared by the
 * whole suite, so these tests never mutate it — each creates its own
 * uniquely-named area and asserts about THAT one.
 */
final class AreaEditorTest extends DatabaseTestCase
{
    private AdminWorld $world;
    private User $artist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'art_files', 'admin_recovery_codes', 'admin_second_factors',
            'admin_audit_log', 'user_roles', 'pettings', 'creatures', 'users'
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

    private function post(string $path, array $fields): Response
    {
        $fields['_csrf_token'] = $this->world->csrf->token();

        return $this->world->router->dispatch(new Request('POST', $path, $fields, '127.0.0.1'));
    }

    private function get(string $path): Response
    {
        return $this->world->router->dispatch(new Request('GET', $path, [], '127.0.0.1'));
    }

    /** Create a draft area through the real route and return it. */
    private function createArea(): Area
    {
        $response = $this->post('/admin/areas/create', [
            'name' => 'Testwood ' . substr(uniqid(), -8),
        ]);
        preg_match('~/admin/areas/(\d+)~', (string) $response->header('Location'), $match);
        $area = $this->world->areaRepo->findById((int) ($match[1] ?? 0));
        $this->assertNotNull($area, 'creating an area must land on its editor page');

        return $area;
    }

    /** Give an area the publish minimum: one spot and one loot line. */
    private function makePublishable(Area $area): void
    {
        $this->post('/admin/areas/' . $area->id . '/spots', ['spot_x' => ['50'], 'spot_y' => ['50']]);
        $this->post('/admin/areas/' . $area->id . '/loot', ['loot' => '1; nothing; Only leaves.']);
    }

    // --- The publish minimum, named piece by piece ---

    public function testPublishingNamesEveryMissingPiece(): void
    {
        $area = $this->createArea();

        $this->post('/admin/areas/' . $area->id . '/publish', []);

        $flash = $_SESSION['flash'] ?? '';
        $this->assertStringContainsString('still missing', $flash);
        $this->assertStringContainsString('spot', $flash);
        $this->assertStringContainsString('loot', $flash);
        $this->assertFalse($this->world->areaRepo->findById($area->id)->isPublished());
    }

    public function testACompleteAreaPublishesAndUnpublishes(): void
    {
        $area = $this->createArea();
        $this->makePublishable($area);

        $this->post('/admin/areas/' . $area->id . '/publish', []);
        $this->assertNotNull($this->world->areaRepo->findBySlugPublished($area->slug),
            'a published area must be visible where the player routes look');

        $this->post('/admin/areas/' . $area->id . '/unpublish', []);
        $this->assertNull($this->world->areaRepo->findBySlugPublished($area->slug),
            'a draft must be as invisible as a made-up slug');
    }

    // --- The loot field: all-or-nothing, and no typing ever lost ---

    public function testARefusedLootSaveChangesNothingAndNamesTheLines(): void
    {
        $area = $this->createArea();
        $this->post('/admin/areas/' . $area->id . '/loot', ['loot' => '1; nothing; Only leaves.']);

        // One good line and one broken one: ALL-or-nothing means the good
        // line must not land either.
        $refusal = $this->post('/admin/areas/' . $area->id . '/loot', [
            'loot' => "5; nothing; Fine line.\n0; treasure; Broken twice.",
        ]);

        // A refusal lands back on the AREA'S page, where the stashed typing
        // and the form live — not on the list. (It went to the list once:
        // refused results carry no contentId, and the redirect was keyed on
        // it. The typing seemed to vanish; the smoke test caught it.)
        $this->assertSame('/admin/areas/' . $area->id, $refusal->header('Location'));

        $flash = $_SESSION['flash'] ?? '';
        $this->assertStringContainsString('Nothing was saved', $flash);
        $this->assertStringContainsString('Line 2', $flash);
        $loot = $this->world->areaRepo->lootFor($area->id);
        $this->assertCount(1, $loot);
        $this->assertSame('Only leaves.', $loot[0]['message'], 'the old table must survive a refused save');
    }

    public function testRefusedTypingIsKeptOnceThenTheFieldGoesBackToTheTable(): void
    {
        $area = $this->createArea();
        $this->post('/admin/areas/' . $area->id . '/loot', ['loot' => '1; nothing; Only leaves.']);

        $this->post('/admin/areas/' . $area->id . '/loot', ['loot' => '0; nothing; my broken typing']);

        // First visit after the refusal: the typed text is there, flagged.
        $body = $this->get('/admin/areas/' . $area->id)->body();
        $this->assertStringContainsString('my broken typing', $body);
        $this->assertStringContainsString('kept exactly as you typed', $body);

        // The stash is spent by that read: the next visit shows the real table.
        $body = $this->get('/admin/areas/' . $area->id)->body();
        $this->assertStringNotContainsString('my broken typing', $body);
        $this->assertStringContainsString('Only leaves.', $body);
    }

    // --- Spots: percentages or nothing ---

    public function testASpotOffTheSceneIsRefused(): void
    {
        $area = $this->createArea();

        $this->post('/admin/areas/' . $area->id . '/spots', ['spot_x' => ['150'], 'spot_y' => ['50']]);

        $this->assertStringContainsString("isn't on the scene", $_SESSION['flash'] ?? '');
        $this->assertSame([], $this->world->areaRepo->findById($area->id)->spots);
    }

    public function testBlankSpotRowsAreQuietlySkipped(): void
    {
        $area = $this->createArea();

        // The form always posts its three blank add-rows; only real pairs count.
        $this->post('/admin/areas/' . $area->id . '/spots', [
            'spot_x' => ['25', '', ''], 'spot_y' => ['75', '', ''],
        ]);

        $this->assertSame([['x' => 25, 'y' => 75]], $this->world->areaRepo->findById($area->id)->spots);
    }

    // --- The scene picture: Backgrounds only ---

    public function testAPictureOfTheWrongKindIsRefusedForTheScene(): void
    {
        $area = $this->createArea();
        // Upload as SPRITE art, then try to hang it behind the scene.
        $this->world->router->dispatch(new Request(
            'POST', '/admin/pictures/upload',
            ['_csrf_token' => $this->world->csrf->token(), 'kind' => 'sprite'],
            '127.0.0.1', [],
            ['picture' => Pictures::asUpload(Pictures::stillPng(), 'creature.png')]
        ));
        $sprite = $this->world->pictures->all()[0];

        $this->post('/admin/areas/' . $area->id . '/picture', ['art_id' => (string) $sprite->id]);

        $this->assertStringContainsString('filed as', $_SESSION['flash'] ?? '');
        $this->assertNull($this->world->areaRepo->findById($area->id)->backgroundArtId);
    }

    // --- Versions: restore brings the WHOLE place back ---

    public function testRevertRestoresSpotsAndLootTogether(): void
    {
        $area = $this->createArea();
        $this->post('/admin/areas/' . $area->id . '/spots', ['spot_x' => ['10'], 'spot_y' => ['20']]);
        $this->post('/admin/areas/' . $area->id . '/loot', ['loot' => '1; nothing; Old leaves.']);

        // Now change everything...
        $this->post('/admin/areas/' . $area->id . '/spots', ['spot_x' => ['90'], 'spot_y' => ['90']]);
        $this->post('/admin/areas/' . $area->id . '/loot', ['loot' => '1; creature; Something new!']);

        // ...and restore the version whose snapshot still holds the old state.
        $wanted = null;
        foreach ($this->world->speciesVersions->listFor('area', $area->id) as $version) {
            if (($version['snapshot']['loot'][0]['message'] ?? '') === 'Old leaves.'
                && ($version['snapshot']['spots'][0]['x'] ?? null) === 10) {
                $wanted = $version;
                break;
            }
        }
        $this->assertNotNull($wanted, 'the history must hold the old state');
        $this->post('/admin/areas/' . $area->id . '/revert', ['version_id' => (string) $wanted['id']]);

        $restored = $this->world->areaRepo->findById($area->id);
        $this->assertSame([['x' => 10, 'y' => 20]], $restored->spots);
        $loot = $this->world->areaRepo->lootFor($area->id);
        $this->assertCount(1, $loot);
        $this->assertSame('Old leaves.', $loot[0]['message']);
    }

    // --- The seeded wood still guards what its table grants ---

    public function testWhisperingWoodStillGuardsItsLootItems(): void
    {
        $references = $this->world->areaRepo->lootItemReferences();

        $this->assertArrayHasKey('acorn-treat', $references,
            'the seeded loot must keep protecting its items from deletion');
        $this->assertStringContainsString('exploring', implode(' ', $references['acorn-treat']));
    }
}
