<?php

declare(strict_types=1);

/**
 * Front controller — the single entry point for every web request to Felkyo.
 *
 * @package Felkyo
 *
 * WHAT "FRONT CONTROLLER" MEANS: the web server is configured so that every URL
 * on the site is handled by this one file. That gives us a single, predictable
 * place where a request begins, where we wire up the pieces (router, templates,
 * logger) and where we list the routes the site responds to.
 *
 * HOW THIS FITS THE BIGGER PICTURE: read this file top-to-bottom to see the shape
 * of the whole request: start up, build the tools, declare the routes, dispatch.
 */

use Felkyo\Http\Router;
use Felkyo\Core\FileLogger;
use League\Plates\Engine;

// Start up: load the autoloader and configuration, and get our settings back.
$config = require dirname(__DIR__) . '/config/bootstrap.php';

// Build the tools this request may need.
// The logger appends to /logs/app.log (that file is per-machine and gitignored).
$logger = new FileLogger(dirname(__DIR__) . '/logs/app.log');

// Plates is our templating engine: it turns a template file plus some values
// into the final HTML. We point it at the /templates folder.
$templates = new Engine(dirname(__DIR__) . '/templates');

$router = new Router();

// ---- Routes ----
// The "hello" route is here to prove the whole stack works end-to-end: a request
// arrives, the router selects this handler, and it renders a Plates template to
// HTML. Increment 0.3 will wrap this in the real, themed site layout.
$router->get('/', function () use ($templates, $config) {
    return $templates->render('pages/hello', [
        'appName' => $config['app']['name'],
    ]);
});

// ---- Dispatch ----
// Work out what was requested. REQUEST_URI can include a "?query=string", so we
// keep only the path part before handing it to the router.
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router->dispatch($requestMethod, $requestPath);
