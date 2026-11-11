<?php

declare(strict_types=1);

namespace Felkyo\Themes;

/**
 * A selectable theme (M4.1) — a name, colour overrides, whether it switches
 * on easy-reading type (M4.3), and (2026-11-11) its style: fonts, corners,
 * frames, glow, drifting symbols, and its own background and banner art.
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
        public readonly ThemeStyle $style = new ThemeStyle(),
        // Stored file names in the art store (served at /art/<name>), or null
        // for the site's own background tile and painted banner.
        public readonly ?string $backgroundArtName = null,
        public readonly ?string $bannerArtName = null,
        public readonly ?int $backgroundArtId = null,
        public readonly ?int $bannerArtId = null,
    ) {
    }

    /** The banner picture's address, or null for the site's painted logo. */
    public function bannerUrl(): ?string
    {
        return self::artUrl($this->bannerArtName);
    }

    /**
     * /art/<name> for a stored art file. The art store generates these names
     * itself; the pattern check is a second lock, because the result is
     * printed into a stylesheet.
     */
    private static function artUrl(?string $storedName): ?string
    {
        return $storedName !== null && preg_match('/^[A-Za-z0-9._-]+$/', $storedName) === 1 ? '/art/' . $storedName : null;
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
        $declarations = [];
        foreach (ThemeTokens::clean($this->tokens) as $token => $value) {
            $declarations[] = '--' . $token . ': ' . $value . ';';
        }
        // The style's values come only from ThemeStyle's own lists.
        $declarations = array_merge($declarations, $this->style->declarations());

        $css = $declarations === [] ? '' : ':root { ' . implode(' ', $declarations) . ' }';
        $background = self::artUrl($this->backgroundArtName);
        if ($background !== null) {
            $css .= " body { background-image: url('" . $background . "'); }";
        }

        return $css;
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $tokens = json_decode((string) $row['tokens'], true);
        $style = json_decode((string) ($row['style'] ?? ''), true);

        return new self(
            (int) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            ThemeTokens::clean(is_array($tokens) ? $tokens : []),
            (bool) $row['easy_reading'],
            (string) $row['status'],
            ThemeStyle::fromChoices(is_array($style) ? $style : []),
            isset($row['background_name']) ? (string) $row['background_name'] : null,
            isset($row['banner_name']) ? (string) $row['banner_name'] : null,
            isset($row['background_art_id']) ? (int) $row['background_art_id'] : null,
            isset($row['banner_art_id']) ? (int) $row['banner_art_id'] : null,
        );
    }
}
