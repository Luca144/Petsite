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
     * Make a file-download response. The body stays EMPTY and the file is
     * streamed straight from disk in send() — the art backup (M2.8) can be
     * hundreds of megabytes, and pulling that into a PHP string first
     * would be a memory bomb. $downloadName is what the browser saves it
     * as; it is our own generated name, never user input.
     */
    public static function fileDownload(string $filePath, string $downloadName): self
    {
        $response = new self('', 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $downloadName . '"',
            'Content-Length' => (string) (filesize($filePath) ?: 0),
        ]);
        $response->filePath = $filePath;

        return $response;
    }

    // Set only by fileDownload(); send() streams this instead of the body.
    private ?string $filePath = null;

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

        if ($this->filePath !== null) {
            // Stream from disk in chunks (see fileDownload). readfile() does
            // exactly that without buffering the whole file — and the file is
            // removed afterwards: every download so far is a generated temp
            // file (the art backup), and leaving those in the system temp dir
            // was the security review's tidiness note.
            readfile($this->filePath);
            @unlink($this->filePath);

            return;
        }

        echo $this->body;
    }

    /** The file this response streams, if it is a download (for tests). */
    public function downloadPath(): ?string
    {
        return $this->filePath;
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
