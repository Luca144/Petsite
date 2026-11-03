<?php

declare(strict_types=1);

namespace Felkyo\Themes;

/**
 * A selectable colour theme (M4.1) — a name, colour overrides, and whether
 * it switches on easy-reading type (M4.3).
 *
 * @package Felkyo\Themes
 */
final class Theme
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    /** @param array<string, string> $tokens already cleaned by ThemeTokens */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly array $tokens,
        public readonly bool $easyReading,
        public readonly string $status,
    ) {
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * The CSS that applies this theme: one :root rule of custom properties.
     * Safe to print raw ONLY because every value passed ThemeTokens::clean()
     * (allow-listed token names, strict #RRGGBB values) — nothing a person
     * typed can reach the stylesheet any other way.
     */
    public function css(): string
    {
        if ($this->tokens === []) {
            return '';
        }
        $declarations = [];
        foreach (ThemeTokens::clean($this->tokens) as $token => $value) {
            $declarations[] = '--' . $token . ': ' . $value . ';';
        }

        return ':root { ' . implode(' ', $declarations) . ' }';
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $tokens = json_decode((string) $row['tokens'], true);

        return new self(
            (int) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            ThemeTokens::clean(is_array($tokens) ? $tokens : []),
            (bool) $row['easy_reading'],
            (string) $row['status'],
        );
    }
}
