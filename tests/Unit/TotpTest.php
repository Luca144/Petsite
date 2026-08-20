<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Admin\Totp;
use PHPUnit\Framework\TestCase;

/**
 * Pins our TOTP implementation to the published standard.
 *
 * @package Felkyo\Tests\Unit
 *
 * THE POINT OF THESE TESTS: we wrote the algorithm ourselves (see Totp for
 * why), so "does it match what every authenticator app computes?" must be
 * proven, not assumed. RFC 6238's Appendix B publishes official test vectors
 * — known times and the exact codes a correct implementation produces for a
 * known secret. If codeAt() reproduces all of them, our maths is the
 * standard's maths, and any app will agree with our door.
 */
final class TotpTest extends TestCase
{
    // The RFC's shared test secret is the ASCII bytes "12345678901234567890",
    // which is this in base32 (the form our class takes secrets in).
    private const RFC_SECRET_BASE32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private Totp $totp;

    protected function setUp(): void
    {
        $this->totp = new Totp();
    }

    /**
     * The official RFC 6238 vectors (Appendix B, SHA-1 rows). The RFC prints
     * 8-digit codes; apps and our class use the standard 6, which are the
     * same computation truncated one step further — the last 6 digits.
     */
    public function testTheRfc6238TestVectorsAllMatch(): void
    {
        $vectors = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
            20000000000 => '353130',
        ];

        foreach ($vectors as $timestamp => $expectedCode) {
            $this->assertSame(
                $expectedCode,
                $this->totp->codeAt(self::RFC_SECRET_BASE32, $timestamp),
                "The code at time {$timestamp} did not match the RFC vector."
            );
        }
    }

    public function testTheCurrentCodeVerifies(): void
    {
        $now = 1234567890;
        $code = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now);

        $this->assertTrue($this->totp->verify(self::RFC_SECRET_BASE32, $code, $now));
    }

    /**
     * A code from the previous or next 30-second slice still verifies — the
     * clock-drift grace the class documents. Two slices away does not.
     */
    public function testAdjacentTimeSlicesAreAcceptedButDistantOnesAreNot(): void
    {
        $now = 1234567890;

        $previous = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now - 30);
        $next = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now + 30);
        $tooOld = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now - 90);

        $this->assertTrue($this->totp->verify(self::RFC_SECRET_BASE32, $previous, $now));
        $this->assertTrue($this->totp->verify(self::RFC_SECRET_BASE32, $next, $now));
        $this->assertFalse($this->totp->verify(self::RFC_SECRET_BASE32, $tooOld, $now));
    }

    public function testAWrongCodeIsRefused(): void
    {
        $now = 1234567890;
        $rightCode = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now);
        // Any different 6 digits. Flip the last digit so it is provably wrong.
        $wrongCode = substr($rightCode, 0, 5) . ((((int) $rightCode[5]) + 1) % 10);

        $this->assertFalse($this->totp->verify(self::RFC_SECRET_BASE32, $wrongCode, $now));
    }

    /**
     * Junk that is not a 6-digit code is refused before any maths happens —
     * including a recovery-code shape, which the door tries elsewhere.
     */
    public function testMalformedInputIsRefused(): void
    {
        $now = 1234567890;

        $this->assertFalse($this->totp->verify(self::RFC_SECRET_BASE32, '', $now));
        $this->assertFalse($this->totp->verify(self::RFC_SECRET_BASE32, '12345', $now));
        $this->assertFalse($this->totp->verify(self::RFC_SECRET_BASE32, 'abcdef', $now));
        $this->assertFalse($this->totp->verify(self::RFC_SECRET_BASE32, 'A3F9-2C71', $now));
    }

    /**
     * People paste codes as "123 456" or with edge spaces; the digits are the
     * code, so those must still verify.
     */
    public function testSpacesInATypedCodeAreForgiven(): void
    {
        $now = 1234567890;
        $code = $this->totp->codeAt(self::RFC_SECRET_BASE32, $now);
        $spaced = ' ' . substr($code, 0, 3) . ' ' . substr($code, 3) . ' ';

        $this->assertTrue($this->totp->verify(self::RFC_SECRET_BASE32, $spaced, $now));
    }

    /**
     * A fresh secret is 32 base32 characters (20 random bytes), and two in a
     * row are different — the "random" part is load-bearing.
     */
    public function testGeneratedSecretsAreWellFormedAndDistinct(): void
    {
        $first = $this->totp->generateSecret();
        $second = $this->totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $first);
        $this->assertNotSame($first, $second);
    }

    /**
     * A generated secret round-trips: the code our class computes from it
     * verifies against it. This is what enrolment relies on.
     */
    public function testAGeneratedSecretRoundTrips(): void
    {
        $secret = $this->totp->generateSecret();
        $now = time();

        $this->assertTrue($this->totp->verify($secret, $this->totp->codeAt($secret, $now), $now));
    }

    public function testTheProvisioningUriNamesTheSiteTheAccountAndTheSecret(): void
    {
        $uri = $this->totp->provisioningUri('ABC234', 'skerro');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('skerro', $uri);
        $this->assertStringContainsString('secret=ABC234', $uri);
        $this->assertStringContainsString('issuer=Felkyo', $uri);
    }
}
