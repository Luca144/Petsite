<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\Role;
use Felkyo\Admin\SettingsCatalog;
use Felkyo\Admin\SiteSettingsRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;
use Felkyo\Users\User;

/**
 * The settings screen: what it changes, and everything it refuses.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.8 plan's promises: only the catalog is editable, bounds hold at
 * save AND at read (a hand-planted row is ignored), the blocklist can
 * never be emptied, reset returns the built-in value, every change is
 * audited, and the maintenance button needs its ticked confirmation.
 */
final class SettingsTest extends DatabaseTestCase
{
    private AdminWorld $world;
    private User $coder;
    private SiteSettingsRepository $settings;
    private SettingsCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'site_settings', 'admin_recovery_codes', 'admin_second_factors',
            'admin_audit_log', 'user_roles', 'pettings', 'creatures', 'users'
        );
        $_SESSION = [];
        $this->world = AdminWorld::build($this->connection);
        $this->coder = $this->world->makeStaff('kat', Role::Coder);
        $this->world->logIn($this->coder);
        $this->world->passDoorFor($this->coder);
        $this->settings = new SiteSettingsRepository($this->connection);
        $this->catalog = new SettingsCatalog();
    }

    protected function tearDown(): void
    {
        $this->world->cleanUpArtFolder();
        parent::tearDown();
    }

    private function post(string $path, array $fields = []): Response
    {
        $fields['_csrf_token'] = $this->world->csrf->token();

        return $this->world->router->dispatch(new Request('POST', $path, $fields, '127.0.0.1'));
    }

    /** The config that results from the stored overrides — the read path. */
    private function liveConfig(): array
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';

        return $this->catalog->apply($config, $this->settings->overrides());
    }

    public function testTheScreenRendersEverySettingWithItsSource(): void
    {
        $body = $this->world->router
            ->dispatch(new Request('GET', '/admin/settings', [], '127.0.0.1'))->body();

        $this->assertStringContainsString('Registration', $body);
        $this->assertStringContainsString('built-in', $body);
        $this->assertStringContainsString('Back up the art', $body);
    }

    public function testAChangedNumberTakesEffectOnTheReadPathAndIsAudited(): void
    {
        $this->post('/admin/settings/xp_per_level', ['value' => '40']);

        $this->assertStringContainsString('Saved', $_SESSION['flash'] ?? '');
        $this->assertSame(40, $this->liveConfig()['gameplay']['growth']['xp_per_level']);
        $this->assertContains('setting.changed', array_column($this->world->auditLog->recent(5), 'action'));
    }

    public function testSwitchingRegistrationOffReachesTheConfigEveryConsumerReads(): void
    {
        // An unticked checkbox posts no "on" — that IS the off state.
        $this->post('/admin/settings/registration_open', ['value' => '']);

        $this->assertFalse($this->liveConfig()['app']['registration_open']);
    }

    public function testAnOutOfBoundsValueIsRefusedAtSaveWithNothingStored(): void
    {
        $this->post('/admin/settings/xp_per_level', ['value' => '999999']);

        $this->assertStringContainsString('goes from', $_SESSION['flash'] ?? '');
        $this->assertArrayNotHasKey('xp_per_level', $this->settings->overrides());
    }

    public function testAKeyOutsideTheCatalogIsRefused(): void
    {
        $this->post('/admin/settings/database_password', ['value' => 'oops']);

        $this->assertStringContainsString('not one of the editable settings', $_SESSION['flash'] ?? '');
        $this->assertSame([], $this->settings->overrides());
    }

    public function testTheBlocklistMayShrinkButNeverEmpty(): void
    {
        $this->post('/admin/settings/blocked_words', ['value' => "  \n \n"]);

        $this->assertStringContainsString('never be emptied', $_SESSION['flash'] ?? '');
        $this->assertArrayNotHasKey('blocked_words', $this->settings->overrides());

        // Shrinking to one word is fine — and it lands lowercased, deduped.
        $this->post('/admin/settings/blocked_words', ['value' => "Spam\nspam\n"]);
        $this->assertSame(['spam'], $this->liveConfig()['moderation']['blocked_words']);
    }

    public function testResetPutsTheBuiltInValueBack(): void
    {
        $this->post('/admin/settings/xp_per_level', ['value' => '40']);
        $this->post('/admin/settings/xp_per_level/reset', []);

        $this->assertStringContainsString('built-in value', $_SESSION['flash'] ?? '');
        $this->assertArrayNotHasKey('xp_per_level', $this->settings->overrides());
        $this->assertSame(20, $this->liveConfig()['gameplay']['growth']['xp_per_level']);
    }

    public function testAValuePlantedStraightIntoTheTableIsIgnoredOnRead(): void
    {
        // The repository stores without judging; the CATALOG judges on read.
        $this->settings->set('xp_per_level', 999999);
        $this->settings->set('some_invented_key', 'whatever');

        $config = $this->liveConfig();

        $this->assertSame(20, $config['gameplay']['growth']['xp_per_level'],
            'an out-of-bounds row must be ignored, not applied');
        $this->assertArrayNotHasKey('some_invented_key', $config);
    }

    public function testClearingTheRateLimitsNeedsTheTickAndThenReallyClears(): void
    {
        $hits = new RateLimitRepository($this->connection);
        $hits->recordHit('login', '10.0.0.1');

        // Without the tick: nothing happens, and the sentence says why.
        $this->post('/admin/maintenance/clear-rate-limits', []);
        $this->assertStringContainsString('tick the little box', $_SESSION['flash'] ?? '');
        $this->assertSame(1, $hits->countRecentHits('login', '10.0.0.1', 3600));

        // With it: the slate is clean, and the audit log says who wiped it.
        $this->post('/admin/maintenance/clear-rate-limits', ['confirm' => 'yes']);
        $this->assertSame(0, $hits->countRecentHits('login', '10.0.0.1', 3600));
        $this->assertContains('maintenance.rate_limits_cleared', array_column($this->world->auditLog->recent(5), 'action'));
    }
}
