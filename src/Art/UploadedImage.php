<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * A picture that has passed every check and is ready to be stored.
 *
 * @package Felkyo\Art
 *
 * Only ImageValidator makes one of these, which is the point: holding an
 * UploadedImage MEANS the bytes were read, recognised, measured, capped and
 * stripped of metadata. Everything downstream (the store, the repository,
 * the audit log) can trust it without re-checking.
 *
 * $bytes are the bytes to STORE — after the metadata strip (or the re-encode
 * for still PNGs) — not the bytes that arrived. $storedName is derived from
 * them, so the same picture always gets the same name.
 */
final class UploadedImage
{
    /**
     * @param string $type         'gif', 'png' or 'webp' — decided by reading
     *                             the file, never by its name.
     * @param int    $width        Pixels, from the file's header.
     * @param int    $height       Pixels, from the file's header.
     * @param bool   $isAnimated   More than one frame.
     * @param string $bytes        The bytes to store (see class note).
     * @param string $originalName The browser's filename, sanitised, for
     *                             display only — never used as a path.
     */
    public function __construct(
        public readonly string $type,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $isAnimated,
        public readonly string $bytes,
        public readonly string $originalName,
    ) {
    }

    /**
     * The name the picture is stored and served under: the SHA-256 of its
     * bytes plus its real extension. Same bytes → same name (a duplicate
     * upload is a no-op); changed bytes → a new name (a browser that cached
     * the old picture for a year never sees it again — M2.3's replacement
     * rule, for free).
     */
    public function storedName(): string
    {
        return hash('sha256', $this->bytes) . '.' . $this->type;
    }

    public function byteCount(): int
    {
        return strlen($this->bytes);
    }
}
