<?php

declare(strict_types=1);

namespace Felkyo\Texts;

/**
 * Turns a sentence's key into the words to show: the change made in the
 * panel if there is one, otherwise the original from the template.
 *
 * @package Felkyo\Texts
 *
 * Used through the template functions registered in index.php:
 *   $this->t('key', 'Original words', ['name' => $creature->name])
 *
 * SAFE BY CONSTRUCTION. toHtml() escapes the words FIRST, then adds the only
 * two pieces of formatting there are — **bold** and *slanted* — so nothing
 * typed in the panel can become a tag. Values filled into {gaps} (a
 * creature's name, a number) are escaped the same way.
 *
 * The changed sentences are read once per page, on first use.
 */
final class TextCatalogue
{
    /** @var array<string, array{body: string, original: ?string}>|null */
    private ?array $changes = null;

    /** @param SiteTextRepository|null $texts null = no changes at all (tests, tools) */
    public function __construct(private ?SiteTextRepository $texts = null)
    {
    }

    /** The words for a key: the changed version, or the original given. */
    public function words(string $key, string $original): string
    {
        return $this->changes()[$key]['body'] ?? $original;
    }

    public function isChanged(string $key): bool
    {
        return isset($this->changes()[$key]);
    }

    /**
     * A message the code sent (a flash, a form error), with any change made
     * to its pattern applied. Only CHANGED message patterns are tried, so
     * this costs nothing until someone edits a message.
     */
    public function message(string $shown): string
    {
        foreach ($this->changes() as $change) {
            if ($change['original'] === null) {
                continue;
            }
            $filled = self::refill($change['original'], $change['body'], $shown);
            if ($filled !== null) {
                return $filled;
            }
        }

        return $shown;
    }

    /**
     * If $shown was made from the pattern $original, the same values put into
     * $changed (whose gaps are {1}, {2} …). Null if it doesn't fit.
     */
    public static function refill(string $original, string $changed, string $shown): ?string
    {
        $values = self::valuesIn($original, $shown);
        if ($values === null) {
            return null;
        }

        return preg_replace_callback('/\{(\d+)\}/', static fn (array $gap): string => $values[(int) $gap[1] - 1] ?? $gap[0], $changed);
    }

    /** The values filled into a pattern's gaps, or null if $shown doesn't fit the pattern. @return string[]|null */
    public static function valuesIn(string $original, string $shown): ?array
    {
        $pieces = preg_split('/%(?:\d+\$)?[sd]/', $original) ?: [$original];
        $pattern = implode('(.+?)', array_map(static fn (string $piece): string => preg_quote(str_replace('%%', '%', $piece), '/'), $pieces));
        if (preg_match('/^' . $pattern . '$/su', $shown, $matches) !== 1) {
            return null;
        }

        return array_slice($matches, 1);
    }

    /**
     * Words → safe HTML: escape, fill {gaps} with escaped values, then
     * **bold** and *slanted*.
     *
     * @param array<string, string|int> $values
     */
    public static function toHtml(string $words, array $values = []): string
    {
        $html = htmlspecialchars($words, ENT_QUOTES, 'UTF-8');
        foreach ($values as $name => $value) {
            $html = str_replace('{' . $name . '}', htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $html);
        }
        $html = preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', $html) ?? $html;

        return preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/su', '<em>$1</em>', $html) ?? $html;
    }

    /** @return array<string, array{body: string, original: ?string}> */
    private function changes(): array
    {
        return $this->changes ??= $this->texts?->all() ?? [];
    }
}
