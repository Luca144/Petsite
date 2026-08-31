<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Art\ArtKind;
use Felkyo\Creatures\Species;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Tests\Support\Pictures;
use Felkyo\Users\User;

/**
 * The species editor: what it builds, and everything it refuses.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.3 plan's promises, as tests: a draft can never be ACQUIRED but
 * always RENDERS (the asymmetry, pinned both ways); publishing names its
 * missing pieces; revert restores; a picture of the wrong kind is refused;
 * a picture a species uses refuses deletion; hostile words render inert.
 *
 * NOTE ON FIXTURES: the three seeded species are migration data shared by
 * the whole suite, so these tests never clear the species table — each
 * creates its own uniquely-named species and asserts about THAT one.
 */
final class SpeciesEditorTest extends DatabaseTestCase
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

    /** A name no other test run has used. */
    private function freshName(): string
    {
        return 'Testling ' . substr(uniqid(), -8);
    }

    private function post(string $path, array $fields): Response
    {
        $fields['_csrf_token'] = $this->world->csrf->token();

        return $this->world->router->dispatch(new Request('POST', $path, $fields, '127.0.0.1'));
    }

    /** Create a draft through the real route and return it. */
    private function createDraft(?string $name = null): Species
    {
        $name = $name ?? $this->freshName();
        $response = $this->post('/admin/creatures/create', ['name' => $name]);
        preg_match('~/admin/creatures/(\d+)~', (string) $response->header('Location'), $match);
        $species = $this->world->speciesRepo->findById((int) ($match[1] ?? 0));
        $this->assertNotNull($species, 'creating a species must land on its editor page');

        return $species;
    }

    /** Upload a sprite picture and return its art id. */
    private function uploadSprite(): int
    {
        $this->world->router->dispatch(new Request(
            'POST', '/admin/pictures/upload',
            ['_csrf_token' => $this->world->csrf->token(), 'kind' => 'sprite'],
            '127.0.0.1', [],
            ['picture' => Pictures::asUpload(Pictures::animatedGif(random_int(11, 60000)), 'sprite.gif')]
        ));
        $pictures = $this->world->pictures->all();
        $this->assertNotEmpty($pictures);

        return $pictures[0]->id;
    }

    /** Give every stage a sprite so the species can publish. */
    private function makePublishable(Species $species): void
    {
        $artId = $this->uploadSprite();
        foreach ($this->world->stageNames as $stage) {
            $this->post('/admin/creatures/' . $species->id . '/picture/' . $stage, ['art_id' => (string) $artId]);
        }
        $this->post('/admin/creatures/' . $species->id . '/words', [
            'name' => $species->name,
            'flavour_text' => 'A small test being.',
            'lore' => '',
        ]);
    }

    // --- Creating ---

    public function testCreatingASpeciesMakesADraftWithASensibleSlug(): void
    {
        $species = $this->createDraft('Mossy Wumple ' . substr(uniqid(), -6));

        $this->assertFalse($species->isPublished());
        $this->assertFalse($species->isAdoptable, 'a new draft must not be adoptable');
        $this->assertFalse($species->isStarter);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $species->slug);
        $this->assertSame('species.created', $this->world->auditLog->recent(1)[0]['action']);
    }

    public function testASecondSpeciesWithTheSameNameIsRefused(): void
    {
        $name = $this->freshName();
        $this->createDraft($name);

        $response = $this->post('/admin/creatures/create', ['name' => $name]);

        $this->assertStringContainsString('already lives at', $_SESSION['flash'] ?? '');
        $this->assertSame('/admin/creatures', $response->header('Location'));
    }

    // --- The draft asymmetry, both ways ---

    public function testADraftCanNeverBeAcquired(): void
    {
        $draft = $this->createDraft();
        // Even with every acquisition flag set, DRAFT keeps it out of the pools.
        $this->post('/admin/creatures/' . $draft->id . '/settings', [
            'gem_price' => '5', 'is_adoptable' => '1', 'is_starter' => '1',
            'favourite_item_id' => '', 'disliked_item_id' => '',
        ]);

        $adoptableIds = array_map(static fn (Species $s): int => $s->id, $this->world->speciesRepo->findAdoptable());
        $starterIds = array_map(static fn (Species $s): int => $s->id, $this->world->speciesRepo->findStarters());

        $this->assertNotContains($draft->id, $adoptableIds, 'the shop and exploration draw from findAdoptable');
        $this->assertNotContains($draft->id, $starterIds, 'starters draw from findStarters');
    }

    public function testADraftStillRendersForCreaturesThatAlreadyExist(): void
    {
        $draft = $this->createDraft();

        // findById and all() are the paths every creature page and card use.
        $this->assertNotNull($this->world->speciesRepo->findById($draft->id));
        $allIds = array_map(static fn (Species $s): int => $s->id, $this->world->speciesRepo->all());
        $this->assertContains($draft->id, $allIds, 'a creature of a re-drafted species must never render as unknown');
    }

    // --- Publishing ---

    public function testPublishingRefusesWithTheMissingPiecesNamed(): void
    {
        $draft = $this->createDraft();

        $this->post('/admin/creatures/' . $draft->id . '/publish', []);

        $flash = $_SESSION['flash'] ?? '';
        $this->assertStringContainsString('still missing', $flash);
        $this->assertStringContainsString('flavour text', $flash);
        $this->assertStringContainsString('picture for the', $flash);
        $this->assertFalse($this->world->speciesRepo->findById($draft->id)->isPublished());
    }

    public function testACompleteSpeciesPublishesAndCanGoBackToDraft(): void
    {
        $species = $this->createDraft();
        $this->makePublishable($species);

        $this->post('/admin/creatures/' . $species->id . '/publish', []);
        $this->assertTrue($this->world->speciesRepo->findById($species->id)->isPublished());
        $this->assertStringContainsString('is live', $_SESSION['flash'] ?? '');

        $this->post('/admin/creatures/' . $species->id . '/unpublish', []);
        $this->assertFalse($this->world->speciesRepo->findById($species->id)->isPublished());
        $this->assertStringContainsString('keep living', $_SESSION['flash'] ?? '');
    }

    // --- Pictures: the kind rule and the delete guard ---

    public function testAPictureOfTheWrongKindIsRefusedForAStage(): void
    {
        $species = $this->createDraft();
        // Upload as LIBRARY art, then try to hang it on a stage.
        $this->world->router->dispatch(new Request(
            'POST', '/admin/pictures/upload',
            ['_csrf_token' => $this->world->csrf->token(), 'kind' => 'library'],
            '127.0.0.1', [],
            ['picture' => Pictures::asUpload(Pictures::stillPng(), 'big-art.png')]
        ));
        $libraryPicture = $this->world->pictures->all()[0];

        $firstStage = $this->world->stageNames[0];
        $this->post('/admin/creatures/' . $species->id . '/picture/' . $firstStage, [
            'art_id' => (string) $libraryPicture->id,
        ]);

        $this->assertStringContainsString('filed as', $_SESSION['flash'] ?? '');
        $this->assertNull($this->world->speciesRepo->findById($species->id)->spriteArtIdFor($firstStage));
    }

    public function testAPictureASpeciesUsesRefusesDeletion(): void
    {
        $species = $this->createDraft();
        $artId = $this->uploadSprite();
        $firstStage = $this->world->stageNames[0];
        $this->post('/admin/creatures/' . $species->id . '/picture/' . $firstStage, ['art_id' => (string) $artId]);

        $this->post('/admin/pictures/' . $artId . '/delete', []);

        $this->assertStringContainsString('still uses this picture', $_SESSION['flash'] ?? '');
        $this->assertNotNull($this->world->pictures->findById($artId), 'the picture must survive');

        // Unlink, and the same delete goes through.
        $this->post('/admin/creatures/' . $species->id . '/picture/' . $firstStage, ['art_id' => '0']);
        $this->post('/admin/pictures/' . $artId . '/delete', []);
        $this->assertNull($this->world->pictures->findById($artId));
    }

    // --- Versions ---

    public function testRevertRestoresAnOlderVersion(): void
    {
        $species = $this->createDraft();
        $this->post('/admin/creatures/' . $species->id . '/words', [
            'name' => $species->name, 'flavour_text' => 'The first words.', 'lore' => '',
        ]);
        $this->post('/admin/creatures/' . $species->id . '/words', [
            'name' => $species->name, 'flavour_text' => 'The second words.', 'lore' => '',
        ]);

        // The history is newest-first; find the version holding the first words.
        $versions = $this->world->speciesVersions->listFor($species->id);
        $target = null;
        foreach ($versions as $version) {
            if (($version['snapshot']['flavour_text'] ?? null) === 'The first words.') {
                $target = $version['id'];
                break;
            }
        }
        $this->assertNotNull($target, 'the first save must be in the history');

        $this->post('/admin/creatures/' . $species->id . '/revert', ['version_id' => (string) $target]);

        $this->assertSame('The first words.', $this->world->speciesRepo->findById($species->id)->flavourText);
        $this->assertSame('species.reverted', $this->world->auditLog->recent(1)[0]['action']);
    }

    public function testAVersionFromAnotherSpeciesCannotBeRestored(): void
    {
        $first = $this->createDraft();
        $second = $this->createDraft();
        $firstVersionId = $this->world->speciesVersions->listFor($first->id)[0]['id'];

        $this->post('/admin/creatures/' . $second->id . '/revert', ['version_id' => (string) $firstVersionId]);

        $this->assertStringContainsString('isn\'t in this species\' history', $_SESSION['flash'] ?? '');
    }

    // --- Hostile words render inert ---

    public function testAScriptTagSpeciesNameRendersInertOnThePreview(): void
    {
        $species = $this->createDraft();
        $this->post('/admin/creatures/' . $species->id . '/words', [
            'name' => '<script>alert(1)</script>', 'flavour_text' => 'Sneaky.', 'lore' => '',
        ]);

        $preview = $this->world->router->dispatch(
            new Request('GET', '/admin/creatures/' . $species->id . '/preview', [], '127.0.0.1')
        );

        $this->assertSame(200, $preview->statusCode());
        $this->assertStringNotContainsString('<script>alert(1)</script>', $preview->body());
        $this->assertStringContainsString('&lt;script&gt;', $preview->body());
    }

    // --- The editor page itself ---

    public function testTheEditorPageShowsEveryStageAndTheHistory(): void
    {
        $species = $this->createDraft();
        $page = $this->world->router->dispatch(
            new Request('GET', '/admin/creatures/' . $species->id, [], '127.0.0.1')
        );

        $this->assertSame(200, $page->statusCode());
        foreach ($this->world->stageNames as $stage) {
            $this->assertStringContainsString('The ' . $stage . ' stage', $page->body());
        }
        $this->assertStringContainsString('Versions', $page->body());
        $this->assertStringContainsString('draft', $page->body());
    }
}
