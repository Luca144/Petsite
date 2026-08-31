<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in development server — makes it behave like the
 * live server for addresses that look like files but aren't.
 *
 * @package Felkyo\Bin
 *
 * RUN THE SITE LOCALLY WITH:
 *   C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public bin/dev-server-router.php
 *
 * WHY THIS FILE EXISTS. Since M2.2, uploaded pictures are served by the app
 * at addresses like /art/<hash>.gif — the bytes live OUTSIDE public/, and
 * ArtController looks them up and sends them. On the live server (FrankenPHP
 * behind Caddy) a request for a path that is not a real file falls through
 * to index.php, so that works. PHP's built-in development server does NOT
 * do that for paths ending in a picture extension: it looks for the file in
 * public/, finds nothing, and answers "404 No such file or directory" on its
 * own, without ever asking the app. Every uploaded picture was therefore a
 * broken image locally while working perfectly in production — the exact
 * kind of gap the smoke test exists to catch, and it caught it.
 *
 * A router script is the built-in server's official fix: it runs for every
 * request, and returning FALSE means "serve this as a static file yourself",
 * while anything else means the app handles it. So: real files under
 * public/ (the CSS, the artwork in assets/) are served as before; everything
 * else — pages AND /art/... — goes to the front controller, like production.
 */

$requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$publicFile = __DIR__ . '/../public' . $requestedPath;

// A real file inside public/ (stylesheet, script, shipped artwork): let the
// built-in server send it. realpath() keeps ".." tricks inside public/.
$realPublic = realpath(__DIR__ . '/../public');
$realFile = realpath($publicFile);
if ($realFile !== false && $realPublic !== false && is_file($realFile) && str_starts_with($realFile, $realPublic)) {
    return false;
}

// Everything else is the app's — exactly as the live server does it.
require __DIR__ . '/../public/index.php';
