<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Creatures\ContentFilter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ContentFilter — the simple blocked-word check.
 *
 * @package Felkyo\Tests\Unit
 */
final class ContentFilterTest extends TestCase
{
    private ContentFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new ContentFilter(['spam', 'scam']);
    }

    public function testCleanTextIsAllowed(): void
    {
        $this->assertFalseWordCheck('A gentle little creature who loves naps.');
    }

    public function testABlockedWordIsCaught(): void
    {
        $this->assertTrue($this->filter->containsBlockedWord('buy my spam now'));
    }

    public function testTheCheckIsCaseInsensitive(): void
    {
        $this->assertTrue($this->filter->containsBlockedWord('SPAM everywhere'));
    }

    public function testAWordThatMerelyContainsABlockedWordIsAllowed(): void
    {
        // "scamper" contains "scam" but is an innocent word — whole-word matching
        // means it is NOT blocked.
        $this->assertFalseWordCheck('the creature loves to scamper about');
    }

    private function assertFalseWordCheck(string $text): void
    {
        $this->assertFalse($this->filter->containsBlockedWord($text));
    }
}
