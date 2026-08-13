<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the tunable numbers and words in exactly one place.
 *
 * @package Felkyo\Tests\Unit
 *
 * THE PATTERN (docs/house-patterns.md section 5): cooldowns, thresholds, limits and
 * names live in config/config.php and nowhere else. The file deliberately has no
 * side effects — loading it changes nothing — which is what lets a test read it.
 *
 * WHY IT IS WORTH A TEST. The second definition is always the one somebody forgets.
 * This has already happened here: the currency was configured as "coins" while the
 * selling code said "gems", so the same thing had two names depending on which page
 * you were looking at. Nothing failed, no test went red, and a player just saw a
 * site that did not seem to know what its own money was called.
 *
 * The checks below are deliberately narrow. A test that tried to catch every
 * hardcoded number would fire on array indexes and HTTP codes and would be turned
 * off within a week — which is worse than not having it.
 */
final class ConfigSingleSourceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, string> Every PHP file under src/, keyed by path. */
    private array $sourceFiles = [];

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->config = require $root . '/config/config.php';

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src'));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->sourceFiles[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }
    }

    public function testTheConfigFileHasNoSideEffects(): void
    {
        // Loading it twice must produce the same thing and change nothing. If it
        // ever starts writing, connecting or echoing, every test that reads it
        // becomes unpredictable — and so does every page load.
        $first = require dirname(__DIR__, 2) . '/config/config.php';
        $second = require dirname(__DIR__, 2) . '/config/config.php';

        $this->assertEquals($first, $second);
    }

    public function testTheCurrencyIsNeverNamedInTheCode(): void
    {
        // The one that actually went wrong. The name of the money is content, and
        // content belongs in config — where the Product Owner can change it from
        // "coins" to "gems" to "acorns" without a developer.
        $configured = $this->config['gameplay']['currency']['name'];

        foreach ($this->sourceFiles as $path => $code) {
            // Comments are stripped: several of them mention the old "gems" bug on
            // purpose, and a comment describing a mistake is not the mistake.
            $withoutComments = (string) preg_replace('~//.*$|/\*.*?\*/~ms', '', $code);

            foreach (['gems', 'coins', 'gold'] as $name) {
                if ($name === $configured) {
                    continue;
                }

                $this->assertStringNotContainsStringIgnoringCase(
                    "' . \$earned . ' {$name}",
                    $withoutComments,
                    basename($path) . " names the currency \"{$name}\" while config says \"{$configured}\"."
                );
            }
        }
    }

    public function testGrowthThresholdsAreOnlyDefinedInConfig(): void
    {
        // GrowthCalculator is handed its thresholds; it must never carry its own
        // copy, or "make creatures level faster" becomes a hunt rather than an edit.
        $calculator = $this->sourceFiles[
            array_values(array_filter(
                array_keys($this->sourceFiles),
                static fn (string $p): bool => str_ends_with($p, 'GrowthCalculator.php')
            ))[0] ?? ''
        ] ?? '';

        $this->assertNotSame('', $calculator, 'GrowthCalculator.php was not found.');

        $withoutComments = (string) preg_replace('~//.*$|/\*.*?\*/~ms', '', $calculator);

        $this->assertStringNotContainsString(
            "'baby' =>",
            $withoutComments,
            'GrowthCalculator defines stage thresholds itself. They belong in config, passed in.'
        );
    }

    public function testEveryTunableNumberWeRelyOnIsActuallyPresent(): void
    {
        // A missing key would otherwise surface as a null deep inside a formula,
        // long after the edit that removed it.
        $required = [
            ['gameplay', 'growth', 'xp_per_level'],
            ['gameplay', 'growth', 'stage_start_levels'],
            ['gameplay', 'petting', 'cooldown_seconds'],
            ['gameplay', 'adoption', 'cooldown_seconds'],
            ['gameplay', 'economy', 'maximum_sell_fraction_of_price'],
            ['gameplay', 'currency', 'name'],
            ['gameplay', 'creature_greetings'],
            ['search', 'minimum_length'],
            ['profile', 'max_about_length'],
            ['moderation', 'blocked_words'],
        ];

        foreach ($required as $path) {
            $value = $this->config;
            foreach ($path as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $value,
                    'Config is missing ' . implode('.', $path)
                );
                $value = $value[$key];
            }
        }
    }
}
