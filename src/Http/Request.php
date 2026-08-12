<?php

declare(strict_types=1);

namespace Felkyo\Http;

/**
 * Represents one incoming web request.
 *
 * @package Felkyo\Http
 *
 * WHAT THIS CLASS IS: a small, read-only snapshot of what the browser asked for —
 * the method (GET/POST), the path, any submitted form values, and the visitor's
 * IP address. Everything a handler needs about the request comes from here.
 *
 * WHY IT EXISTS: instead of controllers reaching into PHP's global $_POST and
 * $_SERVER directly, they receive a Request. That makes them easy to test — a
 * test can build a Request by hand and call the controller, with no real web
 * server involved (this is exactly how the integration tests work).
 */
final class Request
{
    /**
     * @param string $method    The HTTP method, e.g. "GET" or "POST".
     * @param string $path       The URL path, e.g. "/login".
     * @param array  $postData   Submitted form values (name => value).
     * @param string $clientIp   The visitor's IP address (used for rate limiting).
     * @param array  $queryData  Values from the "?name=value" part of the address.
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $postData,
        private string $clientIp,
        private array $queryData = [],
    ) {
    }

    /**
     * Build a Request from PHP's global request state. This is called once, in
     * the front controller, to turn the real web request into a Request object.
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // REQUEST_URI may include a "?query=string"; we keep only the path part.
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        return new self($method, $path, $_POST, $clientIp, $_GET);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function clientIp(): string
    {
        return $this->clientIp;
    }

    /**
     * Read one submitted form value by name, always as a string. Missing values
     * come back as the default (empty string). We deliberately do NOT trim here —
     * some fields (like passwords) may legitimately contain leading/trailing
     * spaces; trimming is done where it is actually wanted (e.g. for usernames).
     */
    public function input(string $key, string $default = ''): string
    {
        $value = $this->postData[$key] ?? $default;

        // Form values are normally strings, but guard against arrays (e.g. from
        // "name[]" fields) so callers always get a plain string.
        return is_string($value) ? $value : $default;
    }

    /**
     * Read one value from the "?name=value" part of the address.
     *
     * WHY THIS IS SEPARATE FROM input(): the two are genuinely different things,
     * and keeping them apart is what makes a controller readable. input() is what
     * somebody SUBMITTED — a form they filled in and posted. query() is part of
     * the ADDRESS — shareable, bookmarkable, and in the browser's history.
     *
     * A search box belongs in the address, which is why searching produces a URL
     * you can send to somebody. A password does not.
     *
     * THIS METHOD EXISTS BECAUSE ITS ABSENCE WAS A BUG. The search page read its
     * query with input(), which only ever looks at posted values — so the box came
     * back empty every time and search silently found nothing. Worse, the test
     * passed, because it built a Request by hand and put the query in the posted
     * values. It proved the controller worked given input it would never receive.
     */
    public function query(string $key, string $default = ''): string
    {
        $value = $this->queryData[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
    /**
     * Read a submitted list of values, e.g. from checkboxes named "featured[]".
     *
     * WHY THIS IS SEPARATE FROM input(): that method promises a string, and a
     * promise like that is worth keeping — callers rely on it and never have to
     * wonder what type they hold. A field that is genuinely a list gets its own
     * method rather than making every other caller defensive.
     *
     * Everything comes back as strings, and nested arrays are dropped. A browser
     * can send almost any shape here (the field name is just text in the form),
     * so this flattens whatever arrived into the one shape callers expect. What
     * the values MEAN is still the caller's job to check.
     *
     * @return array<int, string>
     */
    public function inputList(string $key): array
    {
        $value = $this->postData[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $flat = [];
        foreach ($value as $entry) {
            if (is_string($entry) || is_int($entry)) {
                $flat[] = (string) $entry;
            }
        }

        return $flat;
    }
}
