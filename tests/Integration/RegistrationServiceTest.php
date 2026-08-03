<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\RegistrationService;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;
use Felkyo\Users\UserValidator;

/**
 * Tests for RegistrationService — the rules for creating a new account.
 *
 * @package Felkyo\Tests\Integration
 */
final class RegistrationServiceTest extends DatabaseTestCase
{
    private RegistrationService $registration;
    private UserRepository $users;
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('pettings', 'creatures', 'users');

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $this->users = new UserRepository($this->connection);
        $this->hasher = new PasswordHasher();
        $validator = new UserValidator($config['security']);

        $this->registration = new RegistrationService($this->users, $validator, $this->hasher);
    }

    public function testAValidRegistrationCreatesTheUser(): void
    {
        $result = $this->registration->register('biscuit', 'biscuit@example.com', 'a-good-password');

        $this->assertTrue($result->isSuccessful());
        $this->assertNotNull($this->users->findByUsername('biscuit'));
    }

    public function testInvalidInputIsRejectedAndNoUserIsCreated(): void
    {
        $result = $this->registration->register('biscuit', 'biscuit@example.com', 'short');

        $this->assertFalse($result->isSuccessful());
        $this->assertNotEmpty($result->errors());
        $this->assertNull($this->users->findByUsername('biscuit'));
    }

    public function testADuplicateUsernameIsRejected(): void
    {
        $this->registration->register('biscuit', 'first@example.com', 'a-good-password');

        $result = $this->registration->register('biscuit', 'second@example.com', 'a-good-password');

        $this->assertFalse($result->isSuccessful());
    }

    public function testADuplicateEmailIsRejected(): void
    {
        $this->registration->register('biscuit', 'shared@example.com', 'a-good-password');

        $result = $this->registration->register('marlow', 'shared@example.com', 'a-good-password');

        $this->assertFalse($result->isSuccessful());
    }

    public function testThePasswordIsStoredHashedNotInPlainText(): void
    {
        $this->registration->register('biscuit', 'biscuit@example.com', 'a-good-password');

        $saved = $this->users->findByUsername('biscuit');
        $this->assertNotNull($saved);
        // The stored value must not be the plain password, but must verify against it.
        $this->assertNotSame('a-good-password', $saved->passwordHash);
        $this->assertTrue($this->hasher->verify('a-good-password', $saved->passwordHash));
    }
}
