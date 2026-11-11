<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Texts\TextCatalogue;
use Felkyo\Texts\TextScanner;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TextCatalogue — words typed in the panel becoming page HTML,
 * and changed message patterns being refilled with the day's values.
 *
 * @package Felkyo\Tests\Unit
 *
 * The words are printed into every page, so the escaping cases come first:
 * nothing typed may ever become a tag, in the words or in a filled-in gap.
 */
final class TextCatalogueTest extends TestCase
{
    public function testTypedTagsAreShownAsTextNeverAsTags(): void
    {
        $html = TextCatalogue::toHtml('<script>alert(1)</script> hello');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testGapValuesAreEscapedToo(): void
    {
        $html = TextCatalogue::toHtml('Hello {name}!', ['name' => '<img src=x onerror=alert(1)>']);

        self::assertSame('Hello &lt;img src=x onerror=alert(1)&gt;!', $html);
    }

    public function testStarsBecomeBoldAndSlanted(): void
    {
        self::assertSame('A <b>bold</b> and <em>soft</em> word', TextCatalogue::toHtml('A **bold** and *soft* word'));
    }

    public function testALoneStarStaysAStar(): void
    {
        self::assertSame('5 * 3 = 15', TextCatalogue::toHtml('5 * 3 = 15'));
    }

    public function testAChangedMessageIsRefilledWithTheDaysValues(): void
    {
        $refilled = TextCatalogue::refill('Moved "%s" %s.', 'Shifted {1} {2}!', 'Moved "Guestbook" up.');

        self::assertSame('Shifted Guestbook up!', $refilled);
    }

    public function testAMessageFromADifferentPatternIsLeftAlone(): void
    {
        self::assertNull(TextCatalogue::refill('Moved "%s" %s.', 'Shifted {1} {2}!', 'Something else entirely.'));
    }

    public function testWithoutAnyChangesTheOriginalWordsAreUsed(): void
    {
        self::assertSame('Walk the tideline', (new TextCatalogue())->words('shore.walk', 'Walk the tideline'));
    }

    public function testAPatternIsShownWithNumberedGaps(): void
    {
        self::assertSame('Report #{1} is now marked {2}. 100%', TextScanner::friendly('Report #%d is now marked %s. 100%%'));
    }
}
