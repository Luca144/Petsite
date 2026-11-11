<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Feedback\BugReportService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BugReportService::sitePath — the check that the "page this is
 * about" is an address on this site.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHY THIS HAS ITS OWN TEST: the address is shown to staff as a link, and
 * the player goes back to it after sending. The first version of the check
 * had a broken pattern that silently turned EVERY address into "/" — the
 * site still worked, it just never said which page a bug was on. Each case
 * below is one way the check must hold.
 */
final class BugReportPathTest extends TestCase
{
    public function testAnAddressOnThisSiteIsKept(): void
    {
        self::assertSame('/creatures', BugReportService::sitePath('/creatures'));
        self::assertSame('/creature/5?tab=words', BugReportService::sitePath('/creature/5?tab=words'));
    }

    public function testAnOutsideAddressBecomesTheHomePage(): void
    {
        self::assertSame('/', BugReportService::sitePath('https://elsewhere.example/'));
    }

    public function testADoubleSlashIsRefusedBecauseBrowsersReadItAsAnotherSite(): void
    {
        self::assertSame('/', BugReportService::sitePath('//elsewhere.example'));
    }

    public function testStrangeCharactersAreRefused(): void
    {
        self::assertSame('/', BugReportService::sitePath('/a b'));
        self::assertSame('/', BugReportService::sitePath('/\\elsewhere'));
        self::assertSame('/', BugReportService::sitePath('/"><script>'));
    }

    public function testAnOverlongAddressIsRefused(): void
    {
        self::assertSame('/', BugReportService::sitePath('/' . str_repeat('a', 400)));
    }
}
