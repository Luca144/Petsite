<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * Where picture BYTES live — the one boundary between the site and its disk.
 *
 * @package Felkyo\Art
 *
 * WHY AN INTERFACE, in a codebase that avoids abstraction for its own sake:
 * this is the single place the M2.2 decision memo said must be swappable.
 * Today the pictures live on a Railway volume (VolumeImageStore). When Felkyo
 * has a domain, they may move to Cloudflare R2 — and that must be a second
 * class implementing these four methods, not a rewrite of the panel, the
 * validator, or any template. Four methods, one job; nothing else about the
 * site is allowed to know where a picture's bytes are.
 *
 * Everything that TALKS to the store hands it already-validated bytes and a
 * name the site generated (a content hash plus extension). The store never
 * sees a browser's filename, and never decides anything about content.
 */
interface ImageStore
{
    /**
     * Write these bytes under this name. Overwriting the same name with the
     * same bytes is harmless (a content hash means same name = same bytes).
     */
    public function put(string $storedName, string $bytes): void;

    /**
     * Read the bytes back, or null if there is no such picture.
     */
    public function get(string $storedName): ?string;

    /**
     * Remove the picture. Removing one that is already gone is not an error.
     */
    public function delete(string $storedName): void;

    /**
     * Is there a picture under this name?
     */
    public function has(string $storedName): bool;
}
