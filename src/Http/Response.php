<?php

declare(strict_types=1);

namespace Felkyo\Http;

/**
 * Represents the reply we send back for a request.
 *
 * @package Felkyo\Http
 *
 * WHAT THIS CLASS IS: a small container for a web response — the status code
 * (e.g. 200 OK, 404 Not Found), any headers, and the body (the HTML). A
 * controller builds one of these and returns it; the front controller then calls
 * send() to actually emit it to the browser.
 *
 * WHY IT EXISTS: because controllers RETURN a Response instead of printing output
 * or redirecting directly, they can be tested by inspecting the returned object —
 * no output is sent, nothing exits the script. This keeps controllers pure and
 * testable while the plumbing here handles the one place that talks to the browser.
 */
final class Response
{
    /**
     * @param string $body       The response body (usually rendered HTML).
     * @param int    $statusCode  The HTTP status code (200, 404, 302, ...).
     * @param array  $headers     Response headers (name => value).
     */
    public function __construct(
        private string $body,
        private int $statusCode = 200,
        private array $headers = [],
    ) {
    }

    /**
     * Make an HTML response. This is the common case: a page to show.
     */
    public static function html(string $body, int $statusCode = 200): self
    {
        return new self($body, $statusCode, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Make a redirect response — tells the browser to go to another path. We use
     * this after a successful form submission (the "Post/Redirect/Get" pattern),
     * so refreshing the next page does not re-submit the form.
     */
    public static function redirect(string $path, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $path]);
    }

    /**
     * Emit this response to the browser: status code, then headers, then body.
     * This is the only method that actually produces output, and it is called
     * once, from the front controller.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }

    // --- Read-only accessors, used mainly by tests to check what was returned. ---

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Get one header's value, or null if it was not set.
     */
    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }
}
