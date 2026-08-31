<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Art\ArtRepository;
use Felkyo\Art\ImageStore;
use Felkyo\Http\Request;
use Felkyo\Http\Response;

/**
 * Serves one uploaded picture: GET /art/{name}.
 *
 * @package Felkyo\Http\Controllers
 *
 * THE ONLY DOOR TO THE ART FOLDER. The pictures live outside the web root, so
 * nothing reaches them except this route — and this route never touches a
 * path: it takes the name from the URL, refuses anything that is not the
 * exact hash-plus-extension shape, looks it up in art_files, and only then
 * asks the store for bytes. A name the table does not know is a 404 before
 * any disk is read.
 *
 * THE HEADERS ARE THE SECOND HALF OF UPLOAD SAFETY:
 *  - Content-Type comes from OUR record of what the bytes were verified to
 *    be, never from the file or the request.
 *  - X-Content-Type-Options: nosniff tells the browser to believe that
 *    header and never "sniff" the bytes for something more interesting — so
 *    a GIF that is also valid script stays a GIF.
 *  - Cache-Control: a year, immutable. Safe because the name IS the content:
 *    a replaced picture has a new name, so nobody ever sees a stale one, and
 *    a browser fetches each picture once, ever. This is what keeps a
 *    PHP-served picture cheap.
 */
final class ArtController
{
    // Exactly what a stored name looks like. Anything else is a 404 without
    // a lookup.
    private const NAME_SHAPE = '/^[a-f0-9]{64}\.(gif|png|webp)$/';

    private const CONTENT_TYPES = [
        'gif' => 'image/gif',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private ArtRepository $pictures,
        private ImageStore $store,
    ) {
    }

    /**
     * @param array{name: string} $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        $name = $parameters['name'] ?? '';
        if (!preg_match(self::NAME_SHAPE, $name)) {
            return $this->notFound();
        }

        $picture = $this->pictures->findByStoredName($name);
        if ($picture === null) {
            return $this->notFound();
        }

        $bytes = $this->store->get($picture->storedName);
        if ($bytes === null) {
            // The table knows it, the disk does not: a volume that was wiped
            // or not mounted. A 404 for the visitor; the cause belongs in the
            // deployment guide, not in a page.
            return $this->notFound();
        }

        return new Response($bytes, 200, [
            'Content-Type' => self::CONTENT_TYPES[$picture->type] ?? 'application/octet-stream',
            'Content-Length' => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            // Belt and braces with nosniff: even if a browser ignored the type,
            // "inline" plus a fixed image name gives it nothing to run.
            'Content-Disposition' => 'inline; filename="' . $picture->storedName . '"',
        ]);
    }

    /**
     * A plain, un-themed 404. Pictures are fetched by <img>, never by a
     * person, so there is nobody to show the themed page to — and the themed
     * page would be a full HTML document behind an image URL.
     */
    private function notFound(): Response
    {
        return new Response('', 404, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
