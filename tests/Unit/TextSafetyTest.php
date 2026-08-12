<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Safety\ContactDetailDetector;
use Felkyo\Safety\ImpersonationGuard;
use Felkyo\Safety\TextNormaliser;
use Felkyo\Tests\Support\Guards;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the guards on every piece of text a player can write.
 *
 * @package Felkyo\Tests\Unit
 *
 * These matter more than most tests in this project. There are only three places
 * on the whole site where a player chooses their own words, and those three
 * fields carry the weight that messaging would have carried. If a check here
 * quietly stops working, nothing else notices.
 *
 * THE FALSE-POSITIVE CASES ARE AS IMPORTANT AS THE REFUSALS. A filter that
 * refuses ordinary sentences gets switched off, and then protects nobody at all.
 * "A gentle creature who loves naps." was genuinely refused while this was being
 * written — it contains "snap" once the spaces come out — so the innocent cases
 * are tested first and deliberately.
 */
final class TextSafetyTest extends TestCase
{
    // ---- Ordinary writing must get through ----

    #[DataProvider('innocentText')]
    public function testOrdinaryWritingIsNotRefused(string $text): void
    {
        $guard = Guards::textGuard();

        $this->assertTrue(
            $guard->checkLongText($text, 500)->isAccepted(),
            "\"{$text}\" was refused. A filter that refuses ordinary sentences gets turned off."
        );
    }

    public static function innocentText(): array
    {
        return [
            'a plain bio' => ['A gentle creature who loves naps.'],
            'contains "snap" across a word break' => ['She loves naps in the sun.'],
            'a missing space after a full stop' => ['I like cats.Dogs are fine too.'],
            'a percentage and a full stop' => ['She is 100% good.Really.'],
            'a name that contains a platform name' => ['My creature is called Steamy.'],
            'an ordinary sentence with a number' => ['Found in the woods in 2019, very shy.'],
            'punctuation and dashes' => ['Half-asleep, mostly — but wonderful.'],
            'an apostrophe' => ['She’s the best thing I’ve ever found.'],
        ];
    }

    // ---- Contact details: the most important filter on the site ----

    #[DataProvider('contactDetails')]
    public function testContactDetailsAreRefused(string $text): void
    {
        $guard = Guards::textGuard();
        $result = $guard->checkLongText($text, 500);

        $this->assertFalse($result->isAccepted(), "\"{$text}\" got through.");
        // The refusal says what to change (golden rule 3) without quoting the
        // pattern that matched, which would be a lesson in how to word it next time.
        $this->assertStringContainsString('Felkyo', $result->message());
    }

    public static function contactDetails(): array
    {
        return [
            'a full link' => ['come and see https://example.com/me'],
            'a bare domain' => ['find me at example.com'],
            'a www address' => ['www.somewhere.co.uk has my art'],
            'an email address' => ['write to me at mira@example.com'],
            'a platform by name' => ['add me on discord'],
            'a platform, spaced out' => ['add me on d i s c o r d'],
            'a platform, digits for letters' => ['talk to me on d1sc0rd'],
            'a dot spelled out' => ['my page is example dot com'],
            'a bracketed dot' => ['example(dot)com'],
            'an at-handle' => ['@mira_draws on the bird site'],
            'a short platform as a word' => ['my snap is coolkid'],
        ];
    }

    // ---- Characters that exist to hide things ----

    public function testHiddenCharactersAreRefusedRatherThanStripped(): void
    {
        // Refused, not cleaned. Somebody who has typed a right-to-left override
        // into a creature's name is not doing it by accident, and quietly
        // removing it would hide that they tried.
        $guard = Guards::textGuard();

        $withZeroWidth = "Bis\u{200B}cuit";
        $withOverride = "Biscuit\u{202E}";

        $this->assertFalse($guard->checkName($withZeroWidth, 40)->isAccepted());
        $this->assertFalse($guard->checkName($withOverride, 40)->isAccepted());
        $this->assertTrue(TextNormaliser::containsHidingCharacters($withZeroWidth));
    }

    public function testANameThatRendersAsNothingIsRefused(): void
    {
        // A name made only of invisible characters is a player nobody can point
        // at, refer to, report or block.
        $guard = Guards::textGuard();

        $this->assertFalse($guard->checkName("\u{200B}\u{200B}", 40)->isAccepted());
        $this->assertFalse($guard->checkName('   ', 40)->isAccepted());
        $this->assertFalse($guard->checkName('', 40)->isAccepted());
    }

    public function testEdgeAndDoubledWhitespaceInNamesIsRefused(): void
    {
        $guard = Guards::textGuard();

        $this->assertFalse($guard->checkName(' Biscuit', 40)->isAccepted());
        $this->assertFalse($guard->checkName('Biscuit ', 40)->isAccepted());
        $this->assertFalse($guard->checkName('Bis    cuit', 40)->isAccepted());
        $this->assertTrue($guard->checkName('Biscuit Pip', 40)->isAccepted());
    }

    // ---- Blocked words ----

    public function testABlockedWordIsFoundThroughSimpleDisguises(): void
    {
        $guard = Guards::textGuard(['scam']);

        foreach (['this is a scam', 'this is a SCAM', 'this is a sc4m', 's c a m artists'] as $text) {
            $this->assertFalse(
                $guard->checkLongText($text, 500)->isAccepted(),
                "\"{$text}\" got through the blocklist."
            );
        }
    }

    public function testAShortBlockedWordDoesNotTripOnAnInnocentNeighbour(): void
    {
        // "scam" must not refuse "scamper", and must not be found by running
        // words together. This is the rule that keeps the filter usable.
        $guard = Guards::textGuard(['scam']);

        $this->assertTrue($guard->checkLongText('She likes to scamper about.', 500)->isAccepted());
    }

    // ---- Impersonation ----

    public function testANameClaimingToBeStaffIsRefused(): void
    {
        $guard = Guards::textGuard();

        foreach (['Felkyo Admin', 'moderator', 'the_staff', '0fficial', 'Felkyo Helper'] as $name) {
            $this->assertFalse(
                $guard->checkName($name, 40)->isAccepted(),
                "\"{$name}\" was allowed, and could ask another player for their password."
            );
        }
    }

    public function testAnOrdinaryNameIsNotMistakenForStaff(): void
    {
        $guard = Guards::textGuard();

        foreach (['Mira', 'Biscuit', 'Rowan', 'Admiral Pip'] as $name) {
            $this->assertTrue($guard->checkName($name, 40)->isAccepted(), "\"{$name}\" was wrongly refused.");
        }
    }

    #[DataProvider('lookalikeNames')]
    public function testANameThatReadsLikeAnExistingOneIsRefused(string $candidate): void
    {
        $guard = Guards::textGuard();

        $this->assertFalse(
            $guard->checkName($candidate, 40, ['mira'])->isAccepted(),
            "\"{$candidate}\" was allowed alongside \"mira\", and would be mistaken for them."
        );
    }

    public static function lookalikeNames(): array
    {
        return [
            'the same name' => ['mira'],
            'different case' => ['Mira'],
            'rn for m' => ['rnira'],
            'one for i' => ['m1ra'],
            'a Cyrillic a' => ["mir\u{0430}"],
            'an accent' => ['míra'],
            'spaced out' => ['m i r a'],
            'with underscores' => ['m_i_r_a'],
        ];
    }

    public function testGenuinelyDifferentNamesAreAllowedSideBySide(): void
    {
        $guard = Guards::textGuard();

        foreach (['rowan', 'mirabel', 'miro'] as $name) {
            $this->assertTrue(
                $guard->checkName($name, 40, ['mira'])->isAccepted(),
                "\"{$name}\" was wrongly refused as a lookalike of \"mira\"."
            );
        }
    }

    // ---- The cleaned value ----

    public function testAnAcceptedResultHandsBackTheTidiedText(): void
    {
        // Asking for the value is how a caller gets the safe form — so a caller
        // cannot approve a tidied version and then store the raw one.
        $result = Guards::textGuard()->checkLongText('  Hello there.  ', 500);

        $this->assertTrue($result->isAccepted());
        $this->assertSame('Hello there.', $result->value());
    }

    public function testARefusalHandsBackNothingToStore(): void
    {
        $result = Guards::textGuard()->checkLongText('find me at example.com', 500);

        $this->assertFalse($result->isAccepted());
        $this->assertSame('', $result->value());
    }

    // ---- The pieces, directly ----

    public function testTheDetectorExplainsItselfDifferentlyForDifferentProblems(): void
    {
        $detector = new ContactDetailDetector();

        $this->assertStringContainsString('email', (string) $detector->reasonFor('me@example.com'));
        $this->assertStringContainsString('web addresses', (string) $detector->reasonFor('see example.com'));
        $this->assertNull($detector->reasonFor('A gentle creature who loves naps.'));
    }

    public function testTheSkeletonIsStableForNamesThatReadTheSame(): void
    {
        $guard = new ImpersonationGuard();

        $this->assertSame($guard->skeletonOf('mira'), $guard->skeletonOf('M1RA'));
        $this->assertNotSame($guard->skeletonOf('mira'), $guard->skeletonOf('rowan'));
    }
}
