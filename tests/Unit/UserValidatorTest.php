<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Users\UserValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for UserValidator — the registration input rules.
 *
 * @package Felkyo\Tests\Unit
 *
 * These are the "does the form make sense?" rules, tested with clear examples of
 * good and bad input including the edge cases (empty, too short, too long).
 */
final class UserValidatorTest extends TestCase
{
    private UserValidator $validator;

    protected function setUp(): void
    {
        // A representative policy, matching the recommended config defaults.
        $this->validator = new UserValidator([
            'username_min_length' => 3,
            'username_max_length' => 30,
            'password_min_length' => 8,
            'password_max_length' => 72,
            'email_max_length' => 255,
        ]);
    }

    public function testValidInputProducesNoErrors(): void
    {
        $errors = $this->validator->validateRegistration('biscuit', 'biscuit@example.com', 'a-good-password');

        $this->assertSame([], $errors);
    }

    public function testUsernameThatIsTooShortIsRejected(): void
    {
        $errors = $this->validator->validateRegistration('ab', 'a@example.com', 'a-good-password');

        $this->assertNotEmpty($errors);
    }

    public function testUsernameThatIsTooLongIsRejected(): void
    {
        $longName = str_repeat('a', 31);

        $errors = $this->validator->validateRegistration($longName, 'a@example.com', 'a-good-password');

        $this->assertNotEmpty($errors);
    }

    public function testUsernameWithForbiddenCharactersIsRejected(): void
    {
        // A space (and the "!") are not allowed in a username.
        $errors = $this->validator->validateRegistration('bad name!', 'a@example.com', 'a-good-password');

        $this->assertNotEmpty($errors);
    }

    public function testAnEmptyEmailIsRejected(): void
    {
        $errors = $this->validator->validateRegistration('biscuit', '', 'a-good-password');

        $this->assertNotEmpty($errors);
    }

    public function testAMalformedEmailIsRejected(): void
    {
        $errors = $this->validator->validateRegistration('biscuit', 'not-an-email', 'a-good-password');

        $this->assertNotEmpty($errors);
    }

    public function testAPasswordThatIsTooShortIsRejected(): void
    {
        $errors = $this->validator->validateRegistration('biscuit', 'a@example.com', 'short');

        $this->assertNotEmpty($errors);
    }

    public function testAPasswordThatIsTooLongIsRejected(): void
    {
        $tooLong = str_repeat('a', 73);

        $errors = $this->validator->validateRegistration('biscuit', 'a@example.com', $tooLong);

        $this->assertNotEmpty($errors);
    }

    public function testAPasswordExactlyAtTheMinimumLengthIsAccepted(): void
    {
        // Boundary case: exactly 8 characters must be allowed.
        $errors = $this->validator->validateRegistration('biscuit', 'a@example.com', '12345678');

        $this->assertSame([], $errors);
    }

    public function testSeveralProblemsAreAllReported(): void
    {
        // A short username AND a bad email AND a short password → more than one
        // message, so the person sees everything to fix at once.
        $errors = $this->validator->validateRegistration('ab', 'nope', 'short');

        $this->assertGreaterThanOrEqual(3, count($errors));
    }
}
