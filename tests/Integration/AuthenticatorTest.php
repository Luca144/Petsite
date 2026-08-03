<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Authenticator;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for Authenticator — checking login credentials.
 *
 * @package Felkyo\Tests\Integration
 */
final class AuthenticatorTest extends DatabaseTestCase
{
    private Authenticator $authenticator;
    private UserRepository $users;
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $this->users = new UserRepository($this->connection);
        $this->hasher = new PasswordHasher();
        $this->authenticator = new Authenticator($this->users, $this->hasher);

        // Seed one known account to log in against.
        $this->users->create('biscuit', 'biscuit@example.com', $this->hasher->hash('a-good-password'));
    }

    public function testCorrectCredentialsReturnTheUser(): void
    {
        $user = $this->authenticator->attempt('biscuit', 'a-good-password');

        $this->assertNotNull($user);
        $this->assertSame('biscuit', $user->username);
    }

    public function testAWrongPasswordIsRefused(): void
    {
        $this->assertNull($this->authenticator->attempt('biscuit', 'the-wrong-password'));
    }

    public function testAnUnknownUsernameIsRefused(): void
    {
        $this->assertNull($this->authenticator->attempt('nobody', 'a-good-password'));
    }
}
