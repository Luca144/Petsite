<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Users\User;

/**
 * The unfinished list: each kind of incompleteness detected, and cleared.
 *
 * @package Felkyo\Tests\Integration
 *
 * The build plan's own test sentence for M2.6. Everything here is created
 * through the real panel routes with unique names — the seeded content is
 * shared by the whole suite and never touched.
 *
 * ASSERTIONS ARE ROW-SCOPED (rowsLinkingTo): the panel's content tables
 * are never truncated, so drafts from other tests and earlier runs are on
 * the list too. "The page says 'still needs a spot' somewhere" would pass
 * on their account; only THIS fixture's own row proves anything.
 */
final class UnfinishedListTest extends DatabaseTestCase
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

    private function theList(): string
    {
        $response = $this->world->router->dispatch(new Request('GET', '/admin/unfinished', [], '127.0.0.1'));
        $this->assertSame(200, $response->statusCode());

        return $response->body();
    }

    /**
     * Every list row (<li>) whose editor link is exactly $href — see the
     * class note on why assertions must be scoped to a row.
     *
     * @return string[]
     */
    private function rowsLinkingTo(string $href, string $list): array
    {
        preg_match_all('~<li class="admin-unfinished__row">.*?</li>~s', $list, $matches);

        return array_values(array_filter(
            $matches[0],
            static fn (string $row): bool => str_contains($row, '"' . $href . '"')
        ));
    }

    /** Create through a real panel route and return the new thing's id. */
    private function createAndGetId(string $path, array $fields, string $editorPrefix): int
    {
        $response = $this->post($path, $fields);
        preg_match('~' . preg_quote($editorPrefix, '~') . '(\d+)~', (string) $response->header('Location'), $match);
        $id = (int) ($match[1] ?? 0);
        $this->assertGreaterThan(0, $id, 'creating must land on the editor page');

        return $id;
    }

    public function testADraftSpeciesIsListedWithItsMissingPiecesInTheEditorsWords(): void
    {
        $speciesId = $this->createAndGetId(
            '/admin/creatures/create',
            ['name' => 'Listling ' . substr(uniqid(), -8)],
            '/admin/creatures/'
        );

        $rows = $this->rowsLinkingTo('/admin/creatures/' . $speciesId, $this->theList());

        $this->assertCount(1, $rows);
        // The editor's own words — the list reuses missingForPublish, so the
        // two can never disagree.
        $this->assertStringContainsString('still needs', $rows[0]);
        $this->assertStringContainsString('the flavour text', $rows[0]);
    }

    public function testADraftItemIsListedThenMovesToTheNicetyTierWhenPublishedWithoutAnIcon(): void
    {
        $categoryId = $this->world->itemRepo->allCategories()[0]->id;
        $itemId = $this->createAndGetId(
            '/admin/items/create',
            ['name' => 'Listberry ' . substr(uniqid(), -8), 'category_id' => (string) $categoryId],
            '/admin/items/'
        );
        $href = '/admin/items/' . $itemId;

        // Detected: a fresh draft has no price yet.
        $rows = $this->rowsLinkingTo($href, $this->theList());
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('a price of at least 1', $rows[0]);

        // Cleared: give it numbers and publish...
        $this->post('/admin/items/' . $itemId . '/numbers', [
            'price' => '5', 'sell_value' => '0',
            'happiness_bonus' => '0', 'energy_bonus' => '0',
            'category_id' => (string) $categoryId,
        ]);
        $this->post('/admin/items/' . $itemId . '/publish', []);

        // ...and its one remaining row is the gentler tier: no icon of its own.
        $rows = $this->rowsLinkingTo($href, $this->theList());
        $this->assertCount(1, $rows, 'the blocked row must clear once the piece is supplied');
        $this->assertStringNotContainsString('a price of at least 1', $rows[0]);
        $this->assertStringContainsString('no icon of its own', $rows[0]);
    }

    public function testAPublishedAreaWithoutASceneIsANicetyNotABlocker(): void
    {
        $areaId = $this->createAndGetId(
            '/admin/areas/create',
            ['name' => 'Listwood ' . substr(uniqid(), -8)],
            '/admin/areas/'
        );
        $href = '/admin/areas/' . $areaId;

        // Detected while a draft: the publish minimum, piece by piece.
        $rows = $this->rowsLinkingTo($href, $this->theList());
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('at least one spot', $rows[0]);

        // Cleared by finishing and publishing; what remains is the nicety.
        $this->post('/admin/areas/' . $areaId . '/spots', ['spot_x' => ['50'], 'spot_y' => ['50']]);
        $this->post('/admin/areas/' . $areaId . '/loot', ['loot' => '1; nothing; Only leaves.']);
        $this->post('/admin/areas/' . $areaId . '/publish', []);

        $rows = $this->rowsLinkingTo($href, $this->theList());
        $this->assertCount(1, $rows);
        $this->assertStringNotContainsString('still needs', $rows[0]);
        $this->assertStringContainsString('lamplight glow', $rows[0]);
    }

    public function testADraftShopIsListedAsWaitingForTheWord(): void
    {
        $shopId = $this->createAndGetId(
            '/admin/shops/create',
            ['name' => 'Liststore ' . substr(uniqid(), -8)],
            '/admin/shops/'
        );

        $rows = $this->rowsLinkingTo('/admin/shops/' . $shopId, $this->theList());

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('just not published yet', $rows[0]);
    }
}
