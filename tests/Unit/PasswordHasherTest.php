<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Auth\PasswordHasher;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PasswordHasher — the class that hashes and checks passwords.
 *
 * @package Felkyo\Tests\Unit
 */
final class PasswordHasherTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    public function testACorrectPasswordVerifiesAgainstItsHash(): void
    {
        $hash = $this->hasher->hash('a-good-password');

        $this->assertTrue($this->hasher->verify('a-good-password', $hash));
    }

    public function testAWrongPasswordDoesNotVerify(): void
    {
        $hash = $this->hasher->hash('a-good-password');

        $this->assertFalse($this->hasher->verify('the-wrong-password', $hash));
    }

    public function testTheHashIsNotThePlainPassword(): void
    {
        // The stored value must not be the readable password.
        $this->assertNotSame('a-good-password', $this->hasher->hash('a-good-password'));
    }

    public function testTheSamePasswordProducesDifferentHashes(): void
    {
        // Because a random salt is added, hashing the same password twice gives
        // two different hashes — yet both still verify. This is a good property:
        // it stops anyone spotting that two users share a password.
        $firstHash = $this->hasher->hash('a-good-password');
        $secondHash = $this->hasher->hash('a-good-password');

        $this->assertNotSame($firstHash, $secondHash);
        $this->assertTrue($this->hasher->verify('a-good-password', $firstHash));
        $this->assertTrue($this->hasher->verify('a-good-password', $secondHash));
    }
}
