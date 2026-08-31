<?php

declare(strict_types=1);

namespace Felkyo\Exploration;

/**
 * The loot table's plain-line format: reading it, and writing it back.
 *
 * @package Felkyo\Exploration
 *
 * THE FORMAT, one reward per line, three parts split on the first two
 * semicolons (so a message may contain semicolons):
 *
 *     45; nothing;            Only leaves and a soft wind. Nothing this time.
 *     25; item: acorn-treat;  A crunchy little acorn, tucked under a root.
 *     15; creature;           Something small blinks in the undergrowth!
 *
 * weight — a whole number, at least 1 (all-zero tables would crash the
 * reward roll, so they are unwritable). what — `nothing`, `creature`, or
 * `item:` followed by the item's address. message — what the player reads,
 * up to 200 characters. Blank lines are ignored.
 *
 * parse() is ALL-OR-NOTHING: any bad line makes the whole parse fail, with
 * every problem named by line number in plain words — the editor shows them
 * and keeps exactly what was typed, so nothing is ever lost to one typo.
 * format() writes rows back out in the same shape, aligned for reading, so
 * the field always reopens holding a valid, copyable table.
 */
final class LootLineParser
{
    private const MAX_MESSAGE_LENGTH = 200;
    private const MAX_ROWS = 200;

    /**
     * @param array<string, array{id: int, status: string}> $itemsBySlug every
     *        item's address → its id and status, from ItemRepository.
     * @return array{rows: array<int, array{type: string, item_id: ?int, weight: int, message: string}>,
     *               errors: string[], warnings: string[]}
     */
    public function parse(string $text, array $itemsBySlug): array
    {
        $rows = [];
        $errors = [];
        $warnings = [];

        foreach (explode("\n", $text) as $index => $rawLine) {
            $lineNumber = $index + 1;
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            // Three parts at most — the message keeps any further semicolons.
            $parts = array_map('trim', explode(';', $line, 3));
            if (count($parts) < 3) {
                $errors[] = sprintf('Line %d needs three parts: weight; what; message.', $lineNumber);
                continue;
            }
            [$weightPart, $whatPart, $message] = $parts;

            if (!preg_match('/^\d+$/', $weightPart) || (int) $weightPart < 1) {
                $errors[] = sprintf('Line %d: the weight must be a whole number of at least 1.', $lineNumber);
                continue;
            }

            if ($message === '') {
                $errors[] = sprintf('Line %d: the message is missing — it is what the player reads.', $lineNumber);
                continue;
            }
            if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
                $errors[] = sprintf('Line %d: the message is too long — %d characters is the most.', $lineNumber, self::MAX_MESSAGE_LENGTH);
                continue;
            }

            // What is granted: nothing, a creature, or a named item.
            $type = strtolower($whatPart);
            $itemId = null;
            if ($type === 'nothing' || $type === 'creature') {
                // fine as-is
            } elseif (preg_match('/^item\s*:\s*([a-z0-9-]+)$/i', $whatPart, $match)) {
                $slug = strtolower($match[1]);
                if (!isset($itemsBySlug[$slug])) {
                    $errors[] = sprintf('Line %d: there is no item at the address "%s" — check it on the Items screen.', $lineNumber, $slug);
                    continue;
                }
                if (($itemsBySlug[$slug]['status'] ?? '') !== 'published') {
                    // A warning, not a refusal: a draft item as loot grants
                    // fine (owning is never status-filtered) — but the artist
                    // probably wants to know.
                    $warnings[] = sprintf('Line %d: "%s" is still a draft item.', $lineNumber, $slug);
                }
                $type = 'item';
                $itemId = (int) $itemsBySlug[$slug]['id'];
            } else {
                $errors[] = sprintf('Line %d: "%s" isn\'t a thing to grant — use nothing, creature, or item: the-items-address.', $lineNumber, $whatPart);
                continue;
            }

            $rows[] = ['type' => $type, 'item_id' => $itemId, 'weight' => (int) $weightPart, 'message' => $message];
        }

        if ($rows === [] && $errors === []) {
            $errors[] = 'The table is empty — an area needs at least one reward line (even "just leaves" is a line).';
        }
        if (count($rows) > self::MAX_ROWS) {
            $errors[] = sprintf('That is more than %d lines — split the area instead.', self::MAX_ROWS);
        }

        return ['rows' => $rows, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Rows (as AreaRepository::lootFor returns them) → the line format, with
     * the columns padded so the reopened field reads like a table.
     *
     * @param array<int, array{type: string, weight: int, message: string, item: ?string}> $rows
     */
    public function format(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $what = match ($row['type']) {
                'item' => 'item: ' . ($row['item'] ?? '?'),
                'creature' => 'creature',
                default => 'nothing',
            };
            $lines[] = [str_pad((string) $row['weight'], 3, ' ', STR_PAD_LEFT), $what, $row['message']];
        }

        $whatWidth = 0;
        foreach ($lines as [, $what]) {
            $whatWidth = max($whatWidth, mb_strlen($what));
        }

        return implode("\n", array_map(
            static fn (array $line): string => $line[0] . '; ' . str_pad($line[1] . ';', $whatWidth + 1) . ' ' . $line[2],
            $lines
        ));
    }
}
