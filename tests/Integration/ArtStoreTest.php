<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Art\ArtKind;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Tests\Support\Pictures;

/**
 * The art store end to end: upload through the real route, serve through
 * the real route, and the delete rule.
 *
 * @package Felkyo\Tests\Integration
 *
 * Walks the same path a person does — the Pictures form, the served
 * picture's headers, the delete button — and proves the refusals: a bad
 * file flashes its reason and stores nothing; an artist cannot delete a
 * colleague's picture; a made-up name serves nothing; CSRF is required.
 */
final class ArtStoreTest extends DatabaseTestCase
{
    private AdminWorld $world;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'art_files', 'admin_recovery_codes', 'admin_second_factors',
            'admin_audit_log', 'user_roles', 'pettings', 'creatures', 'users'
        );
        $_SESSION = [];
        $this->world = AdminWorld::build($this->connection);
    }

    protected function tearDown(): void
    {
        $this->world->cleanUpArtFolder();
        parent::tearDown();
    }

    /**
     * Post the upload form as the logged-in user, with a real temp file.
     */
    private function upload(string $bytes, string $browserName, string $kind = 'sprite'): Response
    {
        return $this->world->router->dispatch(new Request(
            'POST', '/admin/pictures/upload',
            ['_csrf_token' => $this->world->csrf->token(), 'kind' => $kind],
            '127.0.0.1', [],
            ['picture' => Pictures::asUpload($bytes, $browserName)]
        ));
    }

    private function get(string $path): Response
    {
        return $this->world->router->dispatch(new Request('GET', $path, [], '127.0.0.1'));
    }

    private function deleteAs(int $pictureId): Response
    {
        return $this->world->router->dispatch(new Request(
            'POST', '/admin/pictures/' . $pictureId . '/delete',
            ['_csrf_token' => $this->world->csrf->token()], '127.0.0.1'
        ));
    }

    // --- Upload ---

    public function testAnArtistCanUploadAPictureAndItIsRecordedStoredAndAudited(): void
    {
        $artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($artist);
        $this->world->passDoorFor($artist);

        $response = $this->upload(Pictures::animatedGif(), 'bramble-adult.gif');

        $this->assertSame(302, $response->statusCode());
        $this->assertStringContainsString('is in', $_SESSION['flash'] ?? '');

        $stored = $this->world->pictures->all();
        $this->assertCount(1, $stored);
        $this->assertSame('bramble-adult.gif', $stored[0]->originalName);
        $this->assertSame(ArtKind::Sprite, $stored[0]->kind);
        $this->assertTrue($stored[0]->isAnimated);
        $this->assertSame($artist->id, $stored[0]->uploadedByUserId);
        $this->assertTrue($this->world->imageStore->has($stored[0]->storedName), 'the bytes are on disk');
        $this->assertSame('art.uploaded', $this->world->auditLog->recent(1)[0]['action']);
    }

    public function testADisguisedScriptIsRefusedWithAReasonAndNothingIsStored(): void
    {
        $artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($artist);
        $this->world->passDoorFor($artist);

        $response = $this->upload('<?php system($_GET["c"]); ?>', 'totally-a-sprite.gif');

        $this->assertSame(302, $response->statusCode());
        $this->assertStringContainsString('isn\'t a GIF, PNG or WebP', $_SESSION['flash'] ?? '');
        $this->assertCount(0, $this->world->pictures->all());
        $this->assertSame([], glob($this->world->artFolder . '/*') ?: [], 'nothing was written to disk');
    }

    public function testUploadingTheSameBytesTwiceKeepsOneRow(): void
    {
        $artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($artist);
        $this->world->passDoorFor($artist);

        $this->upload(Pictures::stillPng(), 'first.png');
        $this->upload(Pictures::stillPng(), 'second.png', 'icon');

        $this->assertCount(1, $this->world->pictures->all());
        $this->assertStringContainsString('already here', $_SESSION['flash'] ?? '');
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($artist);
        $this->world->passDoorFor($artist);

        $this->upload(Pictures::stillGif(), 'x.gif', 'weapon');

        $this->assertCount(0, $this->world->pictures->all());
    }

    public function testUploadWithoutACsrfTokenIsRefused(): void
    {
        $artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($artist);
        $this->world->passDoorFor($artist);

        $this->world->router->dispatch(new Request(
            'POST', '/admin/pictures/upload', ['kind' => 'sprite'], '127.0.0.1', [],
            ['picture' => Pictures::asUpload(Pictures::stillGif())]
        ));

        $this->assertCount(0, $this->world->pictures->all());
    }

    // --- Serving ---

    public function testAStoredPictureIsServedWithTheSafeHeaders(): void
    {
        $artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($artist);
        $this->world->passDoorFor($artist);
        $this->upload(Pictures::animatedGif(), 'anim.gif');
        $picture = $this->world->pictures->all()[0];

        // Served to ANYONE — log out first to prove it.
        $_SESSION = [];
        $response = $this->get($picture->url());

        $this->assertSame(200, $response->statusCode());
        $this->assertSame('image/gif', $response->header('Content-Type'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertStringContainsString('immutable', (string) $response->header('Cache-Control'));
        $this->assertStringStartsWith('GIF89a', $response->body());
        $this->assertStringNotContainsString('secret-note', $response->body(), 'served bytes are the stripped ones');
    }

    public function testANameTheTableDoesNotKnowIsA404EvenIfTheShapeIsRight(): void
    {
        $this->assertSame(404, $this->get('/art/' . str_repeat('a', 64) . '.gif')->statusCode());
    }

    public function testANameThatIsNotAHashIsA404WithoutALookup(): void
    {
        foreach (['../../.env', 'index.php', 'x.gif', str_repeat('a', 64) . '.php'] as $name) {
            $this->assertSame(404, $this->get('/art/' . $name)->statusCode(), $name);
        }
    }

    // --- The delete rule ---

    public function testAnArtistMayDeleteTheirOwnPictureButNotAColleagues(): void
    {
        $oki = $this->world->makeStaff('oki', Role::Artist);
        $mira = $this->world->makeStaff('mira', Role::Artist);

        // Oki uploads one.
        $this->world->logIn($oki);
        $this->world->passDoorFor($oki);
        $this->upload(Pictures::stillGif(), 'okis.gif');
        $okisPicture = $this->world->pictures->all()[0];

        // Mira, also an artist, tries to delete it: refused, still there.
        $_SESSION = [];
        $this->world->logIn($mira);
        $this->world->passDoorFor($mira);
        $this->deleteAs($okisPicture->id);

        $this->assertStringContainsString('only delete pictures you uploaded yourself', $_SESSION['flash'] ?? '');
        $this->assertNotNull($this->world->pictures->findById($okisPicture->id));
        $this->assertTrue($this->world->imageStore->has($okisPicture->storedName));

        // Oki deletes their own: gone from the table AND the disk, audited.
        $_SESSION = [];
        $this->world->logIn($oki);
        $this->world->passDoorFor($oki);
        $this->deleteAs($okisPicture->id);

        $this->assertNull($this->world->pictures->findById($okisPicture->id));
        $this->assertFalse($this->world->imageStore->has($okisPicture->storedName));
        $this->assertSame('art.deleted', $this->world->auditLog->recent(1)[0]['action']);
    }

    public function testAnOwnerMayDeleteAnybodysPicture(): void
    {
        $oki = $this->world->makeStaff('oki', Role::Artist);
        $skerro = $this->world->makeStaff('skerro', Role::Owner);

        $this->world->logIn($oki);
        $this->world->passDoorFor($oki);
        $this->upload(Pictures::stillGif(), 'okis.gif');
        $okisPicture = $this->world->pictures->all()[0];

        $_SESSION = [];
        $this->world->logIn($skerro);
        $this->world->passDoorFor($skerro);
        $this->deleteAs($okisPicture->id);

        $this->assertNull($this->world->pictures->findById($okisPicture->id));
    }

    public function testDeletingAPictureThatIsAlreadyGoneIsSaidPlainly(): void
    {
        $skerro = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($skerro);
        $this->world->passDoorFor($skerro);

        $this->deleteAs(424242);

        $this->assertStringContainsString('already gone', $_SESSION['flash'] ?? '');
    }

    // --- The screen ---

    public function testThePicturesScreenShowsTheGridAndOnlyTheDeleteButtonsYouMayUse(): void
    {
        $oki = $this->world->makeStaff('oki', Role::Artist);
        $mira = $this->world->makeStaff('mira', Role::Artist);

        $this->world->logIn($oki);
        $this->world->passDoorFor($oki);
        $this->upload(Pictures::stillGif(), 'okis-sprite.gif');

        $_SESSION = [];
        $this->world->logIn($mira);
        $this->world->passDoorFor($mira);
        $screen = $this->get('/admin/pictures')->body();

        $this->assertStringContainsString('okis-sprite.gif', $screen);
        $this->assertStringContainsString('Creature sprite', $screen);
        $this->assertStringNotContainsString('/delete"', $screen, 'no delete button on a colleague\'s picture');
    }
}
