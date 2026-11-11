<?php

declare(strict_types=1);

namespace Felkyo\Feedback;

/**
 * Checks a bug report a player sends and stores it.
 *
 * @package Felkyo\Feedback
 *
 * WHAT IS CHECKED, AND WHY:
 *   - the description is required and capped, so the panel stays readable
 *     and nobody can fill the database with a novel;
 *   - the page address must be a path on THIS site (one leading "/"), because
 *     it is shown in the panel as a link — an outside address there would be
 *     a link a stranger chose, sitting in front of staff;
 *   - the browser text and screen width are trimmed to sane sizes; they come
 *     from the browser and are only ever displayed, escaped.
 *
 * Who may send one (logged in) and how often (rate limit) is decided by the
 * controller before this is called.
 */
final class BugReportService
{
    /** @param array{min_length: int, max_length: int} $limits from config gameplay.bug_reports */
    public function __construct(
        private BugReportRepository $reports,
        private array $limits,
    ) {
    }

    /**
     * Store the report, or return a plain sentence saying what to fix.
     *
     * @param array{path: string, browser: string, width: string, description: string} $sent
     */
    public function send(int $reporterId, array $sent): ?string
    {
        $description = trim($sent['description']);
        $length = mb_strlen($description);
        if ($length < $this->limits['min_length']) {
            return sprintf('Tell us a little more — at least %d characters, so we can find it.', $this->limits['min_length']);
        }
        if ($length > $this->limits['max_length']) {
            return sprintf('That is a bit long — %d characters at most, please.', $this->limits['max_length']);
        }

        $this->reports->add([
            'reporter' => $reporterId,
            'path' => self::sitePath($sent['path']),
            'browser' => mb_substr(trim($sent['browser']), 0, 300),
            'width' => self::width($sent['width']),
            'description' => $description,
        ]);

        return null;
    }

    /**
     * The page the report is about, as a path on this site — or "/" if what
     * arrived isn't one. Also used to send the player back where they were.
     */
    public static function sitePath(string $path): string
    {
        // One leading "/" and not two ("//elsewhere.com" would be another
        // site), then only the characters an address on this site uses.
        $path = trim($path);
        if (mb_strlen($path) > 300 || preg_match('#^/(?!/)[A-Za-z0-9/_\-.%~?=&+]*$#', $path) !== 1) {
            return '/';
        }

        return $path;
    }

    /** A believable window width in pixels, or null. */
    private static function width(string $width): ?int
    {
        $pixels = filter_var($width, FILTER_VALIDATE_INT, ['options' => ['min_range' => 100, 'max_range' => 10000]]);

        return $pixels === false ? null : $pixels;
    }
}
