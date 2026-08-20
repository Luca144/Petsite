<?php

declare(strict_types=1);

namespace Felkyo\Admin;

/**
 * Time-based one-time passwords (TOTP) — the 6-digit codes an authenticator
 * app shows.
 *
 * @package Felkyo\Admin
 *
 * HOW TOTP WORKS, IN ONE PARAGRAPH: the site and the person's phone share a
 * secret. Every 30 seconds, both independently compute the same 6-digit code
 * from that secret and the current time (an HMAC, truncated to 6 digits —
 * RFC 6238). Proving you know the current code proves you hold the phone,
 * which is the "second factor": a stolen password alone no longer opens the
 * panel door.
 *
 * WHY WE WROTE IT OURSELVES: the whole algorithm is hash_hmac() plus base32,
 * about eighty lines — smaller than any package's README, and CLAUDE.md
 * (section 12) prefers a small amount of our own code over a dependency. The
 * unit tests pin it to the official RFC test vectors, so "did we implement
 * the standard correctly?" is answered by the suite, not by trust.
 *
 * SHA-1 IS CORRECT HERE, not a mistake: TOTP's HMAC-SHA1 is what every
 * authenticator app speaks by default. (SHA-1 is broken for signatures,
 * where collisions matter; an HMAC does not care about collisions.)
 */
final class Totp
{
    // Codes change every 30 seconds and are 6 digits — the defaults every
    // authenticator app assumes when an otpauth:// URI does not say otherwise.
    private const PERIOD_SECONDS = 30;
    private const DIGITS = 6;

    // The RFC 4648 base32 alphabet, which is how authenticator apps expect
    // secrets to be written (letters that survive being read aloud or typed).
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Make a fresh random secret, as base32 text ready to show the person
     * enrolling. 20 random bytes (160 bits) is the strength RFC 4226
     * recommends.
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Does this code match this secret at this moment?
     *
     * We accept the current 30-second slice and one slice either side. Phone
     * clocks drift and people type slowly; without that grace, a code that
     * was valid when the person read it would fail by the time they submit —
     * and "the right code keeps failing" teaches people 2FA is broken.
     * One slice each way is the conventional balance (90 seconds of validity
     * against 1,000,000 possible codes, behind a rate-limited door).
     *
     * hash_equals compares in constant time, so timing cannot leak how much
     * of a guessed code was right.
     */
    public function verify(string $secret, string $code, int $atTimestamp): bool
    {
        // People paste codes with a stray space or as "123 456"; the digits
        // are the code.
        $code = str_replace(' ', '', trim($code));

        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }

        foreach ([0, -1, 1] as $sliceOffset) {
            $expected = $this->codeAt($secret, $atTimestamp + ($sliceOffset * self::PERIOD_SECONDS));
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The code this secret produces at this moment — the same computation the
     * authenticator app does. Public so the enrolment test and the RFC-vector
     * tests can pin it down.
     */
    public function codeAt(string $secret, int $atTimestamp): string
    {
        // Which 30-second slice of time we are in, as an 8-byte big-endian
        // counter — this is the "message" the HMAC signs (RFC 6238 §4).
        $timeSlice = intdiv($atTimestamp, self::PERIOD_SECONDS);
        $counter = pack('N2', ($timeSlice >> 32) & 0xFFFFFFFF, $timeSlice & 0xFFFFFFFF);

        $hash = hash_hmac('sha1', $counter, $this->base32Decode($secret), true);

        // "Dynamic truncation" (RFC 4226 §5.3): the hash's last half-byte
        // picks which 4 bytes of itself become the number, which is then cut
        // to 6 digits. Odd-looking, but it is the standard, byte for byte.
        $offset = ord($hash[19]) & 0x0F;
        $number = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($number % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The otpauth:// address for this secret — the text form of what a QR
     * code would say. Shown at enrolment beside the plain secret: some
     * authenticator apps accept it pasted directly. (We show text instead of
     * a QR image on purpose — a QR library is a dependency this does not
     * need; see the M2.1 plan.)
     */
    public function provisioningUri(string $secret, string $accountName): string
    {
        return 'otpauth://totp/'
            . rawurlencode('Felkyo Creatures') . ':' . rawurlencode($accountName)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode('Felkyo Creatures');
    }

    /**
     * Bytes → base32 text (RFC 4648, no padding — authenticator apps do not
     * want the trailing '=' signs). Works bit by bit: take the next 5 bits,
     * emit the alphabet letter they name.
     */
    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    /**
     * Base32 text → bytes: the exact reverse of the above. Unknown characters
     * are skipped rather than fatal, so a secret pasted with spaces or dashes
     * still decodes (people group long codes to read them).
     */
    private function base32Decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper($encoded)) as $character) {
            $index = strpos(self::BASE32_ALPHABET, $character);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            // A trailing group shorter than a byte is base32 padding, not data.
            if (strlen($chunk) === 8) {
                $bytes .= chr((int) bindec($chunk));
            }
        }

        return $bytes;
    }
}
