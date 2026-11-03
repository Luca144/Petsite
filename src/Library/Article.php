<?php

declare(strict_types=1);

namespace Felkyo\Library;

/**
 * One piece in the Felkyo Gazette — a newspaper article about the NPCs, an
 * announcement, an event write-up (M5.4).
 *
 * @package Felkyo\Library
 *
 * Written by staff in the panel, read by everyone in the library. The body is
 * plain text: blank lines become paragraphs when shown (see the gazette
 * template), and it is ALWAYS escaped — no HTML from the editor ever reaches
 * the page, so a pasted script is shown as text, never run.
 */
final class Article
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    // What kind of piece it is: a Gazette story (library) or a site update
    // (home page + /updates). Same editor for both.
    public const KIND_GAZETTE = 'gazette';
    public const KIND_UPDATE = 'update';

    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $summary,
        public readonly ?string $body,
        public readonly ?int $artId,
        public readonly ?string $artName,
        public readonly string $status,
        public readonly ?string $publishedAt,
        public readonly string $kind = self::KIND_GAZETTE,
    ) {
    }

    public function isUpdate(): bool
    {
        return $this->kind === self::KIND_UPDATE;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** The article's picture address, or null when none is linked. */
    public function artUrl(): ?string
    {
        return $this->artName !== null && $this->artName !== '' ? '/art/' . $this->artName : null;
    }

    /**
     * The body split into paragraphs at blank lines — so the editor can write
     * naturally and the page still gets real <p> elements.
     *
     * @return string[]
     */
    public function paragraphs(): array
    {
        $normalised = str_replace("\r\n", "\n", (string) $this->body);
        $pieces = preg_split('/\n\s*\n/', $normalised) ?: [];

        return array_values(array_filter(array_map('trim', $pieces), static fn (string $p): bool => $p !== ''));
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['slug'],
            (string) $row['title'],
            $row['summary'] ?? null,
            $row['body'] ?? null,
            isset($row['art_id']) ? (int) $row['art_id'] : null,
            $row['art_name'] ?? null,
            (string) $row['status'],
            $row['published_at'] ?? null,
            (string) ($row['kind'] ?? self::KIND_GAZETTE),
        );
    }
}
