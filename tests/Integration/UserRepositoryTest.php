<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use PDOException;

/**
 * Tests for UserRepository — reading and writing users in the database.
 *
 * @package Felkyo\Tests\Integration
 */
final class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        // Start each test with an empty users table (and its children).
        $this->clearTables('pettings', 'creatures', 'users');
        $this->users = new UserRepository($this->connection);
    }

    public function testCreateSavesAndReturnsTheUser(): void
    {
        $user = $this->users->create('biscuit', 'biscuit@example.com', 'hashed-value');

        $this->assertGreaterThan(0, $user->id);
        $this->assertSame('biscuit', $user->username);
        $this->assertSame('biscuit@example.com', $user->email);
        $this->assertSame('hashed-value', $user->passwordHash);
        $this->assertSame(0, $user->currencyBalance);
    }

    public function testFindByUsernameReturnsTheMatchingUser(): void
    {
        $this->users->create('biscuit', 'biscuit@example.com', 'hashed-value');

        $found = $this->users->findByUsername('biscuit');

        $this->assertNotNull($found);
        $this->assertSame('biscuit@example.com', $found->email);
    }

    public function testFindByUsernameReturnsNullWhenThereIsNoMatch(): void
    {
        $this->assertNull($this->users->findByUsername('nobody'));
    }

    public function testFindByEmailReturnsTheMatchingUser(): void
    {
        $this->users->create('biscuit', 'biscuit@example.com', 'hashed-value');

        $this->assertNotNull($this->users->findByEmail('biscuit@example.com'));
    }

    public function testFindByIdReturnsTheMatchingUser(): void
    {
        $created = $this->users->create('biscuit', 'biscuit@example.com', 'hashed-value');

        $found = $this->users->findById($created->id);

        $this->assertNotNull($found);
        $this->assertSame('biscuit', $found->username);
    }

    public function testFindByIdReturnsNullForAnUnknownId(): void
    {
        $this->assertNull($this->users->findById(999999));
    }

    public function testUpdateLastLoginStampsATime(): void
    {
        $created = $this->users->create('biscuit', 'biscuit@example.com', 'hashed-value');
        $this->assertNull($created->lastLoginAt);

        $this->users->updateLastLogin($created->id);

        $reloaded = $this->users->findById($created->id);
        $this->assertNotNull($reloaded);
        $this->assertNotNull($reloaded->lastLoginAt);
    }

    public function testTheDatabaseRefusesADuplicateUsername(): void
    {
        $this->users->create('biscuit', 'first@example.com', 'hashed-value');

        // The unique index on username is the final guarantee against duplicates,
        // even if two requests slipped past the friendly check at the same moment.
        $this->expectException(PDOException::class);
        $this->users->create('biscuit', 'second@example.com', 'hashed-value');
    }

    public function testAdoptionTrackingRecordsAndReadsTheLastAdoptionTime(): void
    {
        $user = $this->users->create('biscuit', 'biscuit@example.com', 'hashed-value');

        // A brand-new user has never adopted.
        $this->assertFalse($this->users->hasAdoptedWithin($user->id, 3600));

        // After marking an adoption, they count as having adopted within the hour.
        $this->users->markAdopted($user->id);
        $this->assertTrue($this->users->hasAdoptedWithin($user->id, 3600));
    }

    public function testAnOldAdoptionIsOutsideTheWindow(): void
    {
        $user = $this->users->create('biscuit', 'biscuit@example.com', 'hashed-value');

        // An adoption two days ago does not count for a one-hour window.
        $this->connection->exec(
            'UPDATE users SET last_adopted_at = NOW() - INTERVAL 2 DAY WHERE id = ' . $user->id
        );

        $this->assertFalse($this->users->hasAdoptedWithin($user->id, 3600));
    }
}
