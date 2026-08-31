<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Exploration\LootLineParser;
use PHPUnit\Framework\TestCase;

/**
 * The loot table's plain-line format: everything it reads, everything it
 * refuses, and that writing a table out and reading it back changes nothing.
 *
 * @package Felkyo\Tests\Unit
 *
 * The parser is what makes broken loot tables unwritable (an all-zero or
 * empty table would crash the reward roll), so most of these tests are
 * refusals — each checking the error names the LINE and speaks plainly.
 */
final class LootLineParserTest extends TestCase
{
    private LootLineParser $parser;

    /** The item world the parser resolves addresses against. */
    private const ITEMS = [
        'acorn-treat' => ['id' => 7, 'status' => 'published'],
        'moon-dust' => ['id' => 9, 'status' => 'draft'],
    ];

    protected function setUp(): void
    {
        $this->parser = new LootLineParser();
    }

    public function testAWholeTableParsesWithBlankLinesIgnored(): void
    {
        $parsed = $this->parser->parse(
            "45; nothing; Only leaves.\n\n25; item: acorn-treat; A crunchy acorn.\n15; creature; A new friend!",
            self::ITEMS
        );

        $this->assertSame([], $parsed['errors']);
        $this->assertSame([], $parsed['warnings']);
        $this->assertSame([
            ['type' => 'nothing', 'item_id' => null, 'weight' => 45, 'message' => 'Only leaves.'],
            ['type' => 'item', 'item_id' => 7, 'weight' => 25, 'message' => 'A crunchy acorn.'],
            ['type' => 'creature', 'item_id' => null, 'weight' => 15, 'message' => 'A new friend!'],
        ], $parsed['rows']);
    }

    public function testTheMessageKeepsItsOwnSemicolons(): void
    {
        $parsed = $this->parser->parse('1; nothing; Leaves; wind; nothing more.', self::ITEMS);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame('Leaves; wind; nothing more.', $parsed['rows'][0]['message']);
    }

    public function testAZeroWeightIsRefusedByLineNumber(): void
    {
        // Weight 0 is the crash-the-reward-roll case the parser exists to prevent.
        $parsed = $this->parser->parse("1; nothing; Fine.\n0; nothing; Never drawn.", self::ITEMS);

        $this->assertCount(1, $parsed['errors']);
        $this->assertStringContainsString('Line 2', $parsed['errors'][0]);
        $this->assertStringContainsString('at least 1', $parsed['errors'][0]);
    }

    public function testALineWithoutThreePartsIsRefused(): void
    {
        $parsed = $this->parser->parse('45; nothing', self::ITEMS);

        $this->assertStringContainsString('Line 1 needs three parts', $parsed['errors'][0] ?? '');
    }

    public function testAMadeUpItemAddressIsRefused(): void
    {
        $parsed = $this->parser->parse('5; item: moon-cheese; Impossible.', self::ITEMS);

        $this->assertStringContainsString('no item at the address "moon-cheese"', $parsed['errors'][0] ?? '');
    }

    public function testADraftItemWarnsButParses(): void
    {
        // Draft loot still grants fine (owning is never status-filtered) — the
        // artist gets a heads-up, not a refusal.
        $parsed = $this->parser->parse('5; item: moon-dust; Glitter.', self::ITEMS);

        $this->assertSame([], $parsed['errors']);
        $this->assertSame(9, $parsed['rows'][0]['item_id']);
        $this->assertStringContainsString('still a draft item', $parsed['warnings'][0] ?? '');
    }

    public function testAnUnknownGrantWordIsRefusedInPlainWords(): void
    {
        $parsed = $this->parser->parse('5; treasure; Gold!', $itemsBySlug = self::ITEMS);

        $this->assertStringContainsString('"treasure" isn\'t a thing to grant', $parsed['errors'][0] ?? '');
    }

    public function testAnEmptyTableIsRefused(): void
    {
        $parsed = $this->parser->parse("\n   \n", self::ITEMS);

        $this->assertStringContainsString('The table is empty', $parsed['errors'][0] ?? '');
    }

    public function testAMissingMessageIsRefused(): void
    {
        $parsed = $this->parser->parse('5; nothing; ', self::ITEMS);

        $this->assertStringContainsString('the message is missing', $parsed['errors'][0] ?? '');
    }

    public function testATooLongMessageIsRefused(): void
    {
        $parsed = $this->parser->parse('5; nothing; ' . str_repeat('a', 201), self::ITEMS);

        $this->assertStringContainsString('too long', $parsed['errors'][0] ?? '');
    }

    public function testEveryBadLineIsNamedNotJustTheFirst(): void
    {
        // All-or-nothing only feels fair if ALL the problems are listed at once.
        $parsed = $this->parser->parse("0; nothing; Bad weight.\n5; treasure; Bad word.", self::ITEMS);

        $this->assertCount(2, $parsed['errors']);
        $this->assertStringContainsString('Line 1', $parsed['errors'][0]);
        $this->assertStringContainsString('Line 2', $parsed['errors'][1]);
    }

    public function testFormatThenParseChangesNothing(): void
    {
        // format() is what refills the field on every visit — if the round
        // trip drifted, every save would quietly rewrite the table.
        $rows = [
            ['type' => 'nothing', 'weight' => 45, 'message' => 'Only leaves.', 'item' => null],
            ['type' => 'item', 'weight' => 25, 'message' => 'A crunchy acorn.', 'item' => 'acorn-treat'],
            ['type' => 'creature', 'weight' => 15, 'message' => 'A new friend!', 'item' => null],
        ];

        $reparsed = $this->parser->parse($this->parser->format($rows), self::ITEMS);

        $this->assertSame([], $reparsed['errors']);
        $this->assertSame([
            ['type' => 'nothing', 'item_id' => null, 'weight' => 45, 'message' => 'Only leaves.'],
            ['type' => 'item', 'item_id' => 7, 'weight' => 25, 'message' => 'A crunchy acorn.'],
            ['type' => 'creature', 'item_id' => null, 'weight' => 15, 'message' => 'A new friend!'],
        ], $reparsed['rows']);
    }
}
