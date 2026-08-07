<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Fills an empty database with a small, believable demo world.
 *
 * @package Felkyo\Seeds
 *
 * WHY THIS EXISTS: the deployed Felkyo is a closed demo with registration turned
 * off, so nobody can sign up and make it look alive. This seeder creates a few
 * demo players, their creatures, and some petting and guestbook activity — so a
 * visitor arrives at a world that already has people in it, without a single real
 * person's data being involved.
 *
 * HOW TO RUN IT (from the project root):
 *   C:\xampp\php\php.exe vendor/robmorgan/phinx/bin/phinx seed:run -e development
 * On the live server, use "-e production". Set DEMO_ACCOUNT_PASSWORD first (see
 * below) or it will stop and tell you to.
 *
 * SAFE TO RUN TWICE, AND USEFUL TWICE: if the demo accounts already exist it does
 * not duplicate them — it updates their password to the current
 * DEMO_ACCOUNT_PASSWORD and says so. That is what makes changing the demo password
 * a single command rather than a puzzle.
 *
 * ABOUT THE PASSWORD: every demo account shares one password, read from the
 * DEMO_ACCOUNT_PASSWORD environment variable. It is deliberately NOT written in
 * this file — a password committed to a public repository is a password everyone
 * has, and these accounts sit on a public URL. It is still hashed with
 * password_hash() exactly like a real one.
 */
final class DemoContent extends AbstractSeed
{
    /** The demo players. Their creatures are listed with them. */
    private const DEMO_PLAYERS = [
        [
            'username' => 'mira',
            'email' => 'mira@example.invalid',
            'coins' => 45,
            'creatures' => [
                ['species' => 'foxlen', 'name' => 'Biscuit', 'xp' => 140, 'happiness' => 12,
                 'bio' => 'Found asleep in a bread basket. Has not stopped napping since.'],
                ['species' => 'mossling', 'name' => 'Clover', 'xp' => 20, 'happiness' => 3,
                 'bio' => 'Smells faintly of rain.'],
            ],
        ],
        [
            'username' => 'tomas',
            'email' => 'tomas@example.invalid',
            'coins' => 20,
            'creatures' => [
                ['species' => 'pebblewing', 'name' => 'Marlow', 'xp' => 60, 'happiness' => 7,
                 'bio' => 'Collects small shiny things and hides them badly.'],
            ],
        ],
        [
            'username' => 'wren',
            'email' => 'wren@example.invalid',
            'coins' => 5,
            'creatures' => [
                ['species' => 'mossling', 'name' => 'Fern', 'xp' => 0, 'happiness' => 1, 'bio' => null],
            ],
        ],
    ];

    public function run(): void
    {
        $password = getenv('DEMO_ACCOUNT_PASSWORD');
        if ($password === false || $password === '') {
            // Stopping with a clear instruction beats seeding accounts nobody can
            // log into, or inventing a weak password nobody knows about.
            throw new RuntimeException(
                'Set DEMO_ACCOUNT_PASSWORD before seeding, e.g. in .env. '
                . 'It becomes the password for every demo account.'
            );
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Running this again on a database that already has the demo world must not
        // duplicate it. But it must not do NOTHING either: the most likely reason
        // somebody runs it a second time is that they changed
        // DEMO_ACCOUNT_PASSWORD and want the demo accounts to use the new one.
        //
        // An earlier version returned here silently, which meant changing the
        // password appeared to work and then did not — the accounts kept the
        // password they were first created with, and nobody could log in. So the
        // second run now updates the password and SAYS what it did.
        if ($this->demoAccountsAlreadyExist()) {
            $this->refreshDemoPasswords($passwordHash);
            $this->say('Demo accounts already existed. Their password is now the current '
                . 'DEMO_ACCOUNT_PASSWORD. Creatures and activity were left untouched.');

            return;
        }

        $speciesIds = $this->speciesIdsBySlug();
        $creatureCount = 0;

        foreach (self::DEMO_PLAYERS as $player) {
            $userId = $this->insertUser($player, $passwordHash);

            foreach ($player['creatures'] as $creature) {
                $this->insertCreature($userId, $speciesIds[$creature['species']], $creature);
                $creatureCount++;
            }
        }

        $this->addSomeActivity();

        $this->say(sprintf(
            'Created %d demo players and %d creatures. Log in as "%s" with DEMO_ACCOUNT_PASSWORD.',
            count(self::DEMO_PLAYERS),
            $creatureCount,
            self::DEMO_PLAYERS[0]['username']
        ));
    }

    /**
     * Set every demo account's password to the current one.
     *
     * The hash is quoted rather than pasted in, because a bcrypt hash is full of
     * "$" characters and would otherwise be a good way to break the statement.
     */
    private function refreshDemoPasswords(string $passwordHash): void
    {
        $usernames = [];
        foreach (self::DEMO_PLAYERS as $player) {
            $usernames[] = $this->quote($player['username']);
        }

        $this->execute(
            'UPDATE users SET password_hash = ' . $this->quote($passwordHash)
            . ' WHERE username IN (' . implode(', ', $usernames) . ')'
        );
    }

    /**
     * Tell the person running the seeder what happened.
     *
     * A script that finishes in silence is indistinguishable from one that did
     * nothing, which is exactly the confusion this seeder caused once already.
     */
    private function say(string $message): void
    {
        $this->getOutput()->writeln(' == ' . $message);
    }

    /**
     * Have the demo accounts already been created? Checked by username, so running
     * the seeder a second time is harmless.
     */
    private function demoAccountsAlreadyExist(): bool
    {
        $existing = $this->fetchRow(
            "SELECT COUNT(*) AS total FROM users WHERE username = " . $this->quote(self::DEMO_PLAYERS[0]['username'])
        );

        return (int) $existing['total'] > 0;
    }

    /**
     * Species live in the database as data, so we look their ids up by slug rather
     * than assuming what number each one was given.
     *
     * @return array<string, int> slug => id
     */
    private function speciesIdsBySlug(): array
    {
        $ids = [];
        foreach ($this->fetchAll('SELECT id, slug FROM species') as $row) {
            $ids[$row['slug']] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $player
     */
    private function insertUser(array $player, string $passwordHash): int
    {
        $this->table('users')->insert([[
            'username' => $player['username'],
            'email' => $player['email'],
            'password_hash' => $passwordHash,
            'currency_balance' => $player['coins'],
        ]])->saveData();

        return (int) $this->fetchRow(
            'SELECT id FROM users WHERE username = ' . $this->quote($player['username'])
        )['id'];
    }

    /**
     * @param array<string, mixed> $creature
     */
    private function insertCreature(int $ownerId, int $speciesId, array $creature): void
    {
        $this->table('creatures')->insert([[
            'owner_id' => $ownerId,
            'species_id' => $speciesId,
            'name' => $creature['name'],
            'xp' => $creature['xp'],
            'happiness' => $creature['happiness'],
            'bio' => $creature['bio'],
            'is_public' => 1,
        ]])->saveData();
    }

    /**
     * A world with no activity looks abandoned, so the demo players pet and sign
     * for each other. Everything is matched up by name in SQL, which means this
     * works whatever ids the inserts above happened to receive.
     */
    private function addSomeActivity(): void
    {
        // Tomas and Wren have each petted Biscuit; Mira has petted Marlow.
        $this->execute(
            "INSERT INTO pettings (creature_id, actor_user_id)
             SELECT c.id, u.id FROM creatures c, users u
              WHERE (c.name = 'Biscuit' AND u.username IN ('tomas', 'wren'))
                 OR (c.name = 'Marlow'  AND u.username = 'mira')"
        );

        // A couple of guestbook signatures. The message keys must exist in
        // config/config.php under gameplay.guestbook.messages.
        $this->execute(
            "INSERT INTO guestbook_entries (creature_id, author_user_id, message_key)
             SELECT c.id, u.id, 'what-a-sweetheart' FROM creatures c, users u
              WHERE c.name = 'Biscuit' AND u.username = 'tomas'"
        );
        $this->execute(
            "INSERT INTO guestbook_entries (creature_id, author_user_id, message_key)
             SELECT c.id, u.id, 'well-cared-for' FROM creatures c, users u
              WHERE c.name = 'Biscuit' AND u.username = 'wren'"
        );
        $this->execute(
            "INSERT INTO guestbook_entries (creature_id, author_user_id, message_key)
             SELECT c.id, u.id, 'passing-through' FROM creatures c, users u
              WHERE c.name = 'Marlow' AND u.username = 'mira'"
        );
    }

    /**
     * Safely quote a value for the few places Phinx's helpers need a literal.
     * The values here are our own constants, never user input, but quoting them
     * properly is the habit to keep.
     */
    private function quote(string $value): string
    {
        return $this->getAdapter()->getConnection()->quote($value);
    }
}
