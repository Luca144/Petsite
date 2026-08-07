<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Checks that configuration is really read from the environment, and that the
 * production defaults are the safe ones.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHY THIS TEST EXISTS — it is guarding a bug that already happened once.
 *
 * The config file originally read settings from $_ENV alone. That works perfectly
 * on a development machine, because the .env file's values are put into $_ENV by
 * phpdotenv. But a hosting platform has no .env file: it supplies settings as real
 * environment variables, and PHP only copies those into $_ENV when a php.ini
 * setting ("variables_order") includes an "E" — which it very often does not.
 *
 * The result would have been silent and bad: on the live server every setting would
 * fall back to its development default, which means **registration would have been
 * OPEN on a site that is supposed to be a closed demo**. Nothing would have looked
 * broken. So these tests set a real environment variable, with $_ENV deliberately
 * cleared, and check the config still sees it.
 */
final class ConfigEnvironmentTest extends TestCase
{
    /** The environment keys these tests interfere with. */
    private const KEYS = ['APP_ENV', 'REGISTRATION_OPEN', 'SHOW_DEMO_NOTICE'];

    /** @var array<string, mixed> */
    private array $savedEnv = [];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    protected function setUp(): void
    {
        // Remember the real values so the rest of the suite is unaffected.
        foreach (self::KEYS as $key) {
            $this->savedEnv[$key] = $_ENV[$key] ?? null;
            $this->savedServer[$key] = $_SERVER[$key] ?? null;
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::KEYS as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            if ($this->savedEnv[$key] !== null) {
                $_ENV[$key] = $this->savedEnv[$key];
            }
            if ($this->savedServer[$key] !== null) {
                $_SERVER[$key] = $this->savedServer[$key];
            }
        }
    }

    /**
     * Load config.php fresh, so it re-reads the environment as it is right now.
     *
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        return require dirname(__DIR__, 2) . '/config/config.php';
    }

    /**
     * The core of it: a REAL environment variable, with nothing in $_ENV, must be
     * seen. This is exactly the situation on a hosting platform.
     */
    public function testASettingIsReadFromARealEnvironmentVariable(): void
    {
        putenv('APP_ENV=production');

        $this->assertSame('production', $this->loadConfig()['app']['environment']);
    }

    /**
     * The safe default: on production, registration is closed unless somebody
     * deliberately opens it. Forgetting to set anything must not open the door.
     */
    public function testRegistrationIsClosedByDefaultInProduction(): void
    {
        putenv('APP_ENV=production');

        $this->assertFalse($this->loadConfig()['app']['registration_open']);
    }

    public function testRegistrationIsOpenByDefaultInDevelopment(): void
    {
        putenv('APP_ENV=development');

        $this->assertTrue($this->loadConfig()['app']['registration_open']);
    }

    /**
     * The default can still be overridden deliberately — otherwise the setting
     * would be useless.
     */
    public function testRegistrationCanBeOpenedExplicitlyInProduction(): void
    {
        putenv('APP_ENV=production');
        putenv('REGISTRATION_OPEN=true');

        $this->assertTrue($this->loadConfig()['app']['registration_open']);
    }

    public function testTheDemoBannerIsShownByDefaultInProductionAndHiddenInDevelopment(): void
    {
        putenv('APP_ENV=production');
        $this->assertTrue($this->loadConfig()['app']['show_demo_notice']);

        putenv('APP_ENV=development');
        $this->assertFalse($this->loadConfig()['app']['show_demo_notice']);
    }

    /**
     * Anything that is not exactly "true" counts as false, so a typo like
     * REGISTRATION_OPEN=yes fails safe rather than opening the site.
     */
    public function testAnUnrecognisedValueFailsSafe(): void
    {
        putenv('APP_ENV=production');
        putenv('REGISTRATION_OPEN=yes');

        $this->assertFalse($this->loadConfig()['app']['registration_open']);
    }
}
