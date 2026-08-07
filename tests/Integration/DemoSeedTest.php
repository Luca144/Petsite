<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Tests\DatabaseTestCase;

/**
 * Tests that the demo seed script actually runs, against the real test database.
 *
 * @package Felkyo\Tests\Integration
 *
 * WHY THIS IS A TEST AND NOT A "we'll find out on the day": the seed script is run
 * exactly once, on the live server, at the least convenient possible moment. A
 * typo in it would only be discovered there. So we run it for real here — the same
 * Phinx command, against felkyo_test — and check the world it builds.
 *
 * It also checks the script is safe to run twice, because sooner or later somebody
 * will run it twice.
 */
final class DemoSeedTest extends DatabaseTestCase
{
    /** Any password will do; the seeder only needs one to exist. */
    private const TEST_DEMO_PASSWORD = 'a-password-for-the-test-run';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('guestbook_entries', 'rate_limit_hits', 'pettings', 'creatures', 'users');
    }

    /**
     * Leave the test database as we found it, so a seeded demo world cannot
     * surprise whichever test happens to run next.
     */
    protected function tearDown(): void
    {
        $this->clearTables('guestbook_entries', 'rate_limit_hits', 'pettings', 'creatures', 'users');
    }

    /**
     * Run the seeder the same way a person would, against the testing environment.
     *
     * @param string|null $password Overrides the demo password for this run, so a
     *                              test can simulate somebody changing it.
     * @return array{exitCode: int, output: string}
     */
    private function runSeeder(?string $password = null): array
    {
        $projectRoot = dirname(__DIR__, 2);

        // The seeder reads this from the environment and refuses to run without it.
        putenv('DEMO_ACCOUNT_PASSWORD=' . ($password ?? self::TEST_DEMO_PASSWORD));

        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($projectRoot . '/vendor/robmorgan/phinx/bin/phinx')
            . ' seed:run -e testing'
            . ' -c ' . escapeshellarg($projectRoot . '/phinx.php');

        exec($command . ' 2>&1', $output, $exitCode);

        return ['exitCode' => $exitCode, 'output' => implode("\n", $output)];
    }

    private function countIn(string $table): int
    {
        return (int) $this->connection->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    public function testTheSeedScriptRunsCleanlyAndBuildsADemoWorld(): void
    {
        $result = $this->runSeeder();

        $this->assertSame(0, $result['exitCode'], "The seeder failed:\n" . $result['output']);

        // Three demo players, each with at least one creature.
        $this->assertSame(3, $this->countIn('users'));
        $this->assertSame(4, $this->countIn('creatures'));

        // And some activity, so the demo does not look abandoned.
        $this->assertGreaterThan(0, $this->countIn('pettings'));
        $this->assertGreaterThan(0, $this->countIn('guestbook_entries'));
    }

    /**
     * Running it a second time must not double every demo account.
     */
    public function testRunningTheSeedScriptTwiceDoesNotDuplicateAnything(): void
    {
        $this->runSeeder();
        $usersAfterFirstRun = $this->countIn('users');
        $creaturesAfterFirstRun = $this->countIn('creatures');

        $second = $this->runSeeder();

        $this->assertSame(0, $second['exitCode'], "The second run failed:\n" . $second['output']);
        $this->assertSame($usersAfterFirstRun, $this->countIn('users'));
        $this->assertSame($creaturesAfterFirstRun, $this->countIn('creatures'));
    }

    /**
     * The bug this test exists for, found on the real deployment:
     *
     * somebody changes DEMO_ACCOUNT_PASSWORD and runs the seeder again, expecting
     * the demo accounts to use the new password. An earlier version returned
     * silently because the accounts already existed, so the change appeared to work
     * and did not — the accounts kept their original password and nobody could log
     * in. Re-running must actually update the password.
     */
    public function testRunningItAgainWithANewPasswordUpdatesTheDemoAccounts(): void
    {
        $this->runSeeder();
        $this->runSeeder('a-completely-different-password');

        $hash = (string) $this->connection
            ->query("SELECT password_hash FROM users WHERE username = 'mira'")
            ->fetchColumn();

        $this->assertTrue(
            password_verify('a-completely-different-password', $hash),
            'Re-running the seeder must set the demo accounts to the current password.'
        );
        $this->assertFalse(
            password_verify(self::TEST_DEMO_PASSWORD, $hash),
            'The old password must no longer work after the seeder is re-run.'
        );
    }

    /**
     * A script that finishes in silence is indistinguishable from one that did
     * nothing — which is exactly how the bug above stayed hidden. Both paths must
     * say what happened.
     */
    public function testTheSeederSaysWhatItDid(): void
    {
        $firstRun = $this->runSeeder();
        $this->assertStringContainsString('Created', $firstRun['output']);

        $secondRun = $this->runSeeder();
        $this->assertStringContainsString('already existed', $secondRun['output']);
    }

    /**
     * Every seeded creature must belong to a seeded user and a real species — a
     * demo world with a broken link in it would show up as an error page.
     */
    public function testEverySeededCreatureHasAnOwnerAndAKnownSpecies(): void
    {
        $this->runSeeder();

        $orphans = (int) $this->connection->query(
            'SELECT COUNT(*) FROM creatures
               LEFT JOIN users ON users.id = creatures.owner_id
               LEFT JOIN species ON species.id = creatures.species_id
              WHERE users.id IS NULL OR species.id IS NULL'
        )->fetchColumn();

        $this->assertSame(0, $orphans);
    }
}
