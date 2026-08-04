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
 * IT SUPPORTS PATH PARAMETERS: a route path may contain a placeholder in curly
 * braces, e.g. "/creature/{id}". That matches "/creature/42" and captures
 * id => "42", which is passed to the handler. This is how pages for a specific
 * thing (a creature, later a user) get their id from the URL.
 *
 * A handler is any callable that receives the Request and an array of captured
 * parameters, and returns a Response: function (Request $r, array $params).
 * Handlers that do not need the parameters can simply ignore the second argument.
 *
 * WHY WE WROTE OUR OWN INSTEAD OF ADDING A LIBRARY: routing for a site this size
 * is a short, readable amount of code, and CLAUDE.md (section 11) prefers that
 * over a dependency.
 *
 * HOW THIS FITS THE BIGGER PICTURE: the front controller (public/index.php)
 * creates one Router, registers every route, and calls dispatch(). That keeps
 * the list of "what URLs exist" in a single, scannable place.
 */
final class Router
{
    /**
     * Every registered route, each as: method, the path split into segments, and
     * the handler. We keep them as a simple list and check them in order.
     *
     * @var array<int, array{method: string, segments: string[], handler: callable}>
     */
    private array $routes = [];

    /**
     * What to run when no route matches. Set by setNotFoundHandler() so the app
     * can show a friendly, themed 404 page. If left unset, a plain message is used.
     *
     * @var callable|null
     */
    private $notFoundHandler = null;

    /**
     * Set the handler used when no route matches (the "404" page). It receives the
     * Request and returns a Response, just like a normal handler.
     */
    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Register a handler for a GET request (a browser asking to view a page).
     */
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * Register a handler for a POST request (a form submitting data that changes
     * something).
     */
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'segments' => $this->splitPath($path),
            'handler' => $handler,
        ];
    }

    /**
     * Find the route matching the request and run its handler, returning the
     * Response. If nothing matches, run the not-found handler (a themed 404 page
     * when one is set), or fall back to a plain message.
     */
    public function dispatch(Request $request): Response
    {
        $requestSegments = $this->splitPath($request->path());

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $parameters = $this->matchSegments($route['segments'], $requestSegments);
            if ($parameters !== null) {
                return ($route['handler'])($request, $parameters);
            }
        }

        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)($request);
        }

        return Response::html('Page not found.', 404);
    }

    /**
     * Break a path into its segments, ignoring leading/trailing slashes. So "/"
     * becomes [] (no segments) and "/creature/42" becomes ["creature", "42"].
     * Trimming the slashes also means "/login" and "/login/" are treated the same.
     *
     * @return string[]
     */
    private function splitPath(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return [];
        }

        return explode('/', $trimmed);
    }

    /**
     * Try to match one route's segments against the request's segments. Returns
     * the captured parameters (an empty array if the route has none) on a match,
     * or null if this route does not match.
     *
     * A "{name}" segment matches any single segment and captures its value; every
     * other segment must match exactly.
     *
     * @param string[] $routeSegments
     * @param string[] $requestSegments
     * @return array<string, string>|null
     */
    private function matchSegments(array $routeSegments, array $requestSegments): ?array
    {
        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $parameters = [];

        foreach ($routeSegments as $index => $routeSegment) {
            $isPlaceholder = str_starts_with($routeSegment, '{') && str_ends_with($routeSegment, '}');

            if ($isPlaceholder) {
                // Strip the braces to get the parameter name, and capture the value.
                $name = substr($routeSegment, 1, -1);
                $parameters[$name] = $requestSegments[$index];
            } elseif ($routeSegment !== $requestSegments[$index]) {
                return null;
            }
        }

        return $parameters;
    }
}
