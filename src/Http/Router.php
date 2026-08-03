<?php

declare(strict_types=1);

namespace Felkyo\Http;

/**
 * A tiny URL router for Felkyo Creatures.
 *
 * @package Felkyo\Http
 *
 * WHAT THIS CLASS IS: it decides which piece of code runs for a given web
 * address. You "register" routes up front (e.g. GET "/" runs the home handler),
 * and then dispatch() looks at the incoming Request and runs the matching one,
 * returning its Response.
 *
 * WHY WE WROTE OUR OWN INSTEAD OF ADDING A LIBRARY: routing for a site this size
 * is a short, readable amount of code, and CLAUDE.md (section 11) prefers that
 * over a dependency. This version matches exact paths, which is all we need so
 * far. When a later increment needs addresses with an id in them (for example
 * "/creature/42"), this is the one class to extend — the developer guide records
 * that as the place to add pattern matching.
 *
 * A handler is any callable that takes the Request and returns a Response.
 *
 * HOW THIS FITS THE BIGGER PICTURE: the front controller (public/index.php)
 * creates one Router, registers every route, and calls dispatch(). That keeps
 * the list of "what URLs exist" in a single, scannable place.
 */
final class Router
{
    /**
     * Registered routes, grouped by HTTP method, then keyed by exact path.
     * Example: $routes['GET']['/'] = (the handler for the home page).
     *
     * @var array<string, array<string, callable>>
     */
    private array $routes = [];

    /**
     * Register a handler for a GET request to an exact path.
     * GET is the method a browser uses when it simply asks to view a page.
     */
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /**
     * Register a handler for a POST request to an exact path.
     * POST is the method used when a form submits data that changes something.
     */
    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    /**
     * Find the handler matching the request and run it, returning its Response.
     * If no route matches, we return a 404 ("page not found") response. The
     * friendly, themed 404 page comes later (increment C.2); for now a plain
     * message is enough.
     */
    public function dispatch(Request $request): Response
    {
        $path = $request->path();

        // Treat "/creatures/" and "/creatures" as the same address by removing a
        // trailing slash, except for the site root which is just "/". This spares
        // us from registering two entries for every route.
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $handler = $this->routes[$request->method()][$path] ?? null;

        if ($handler === null) {
            return Response::html('Page not found.', 404);
        }

        return $handler($request);
    }
}
