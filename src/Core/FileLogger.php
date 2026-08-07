<?php

declare(strict_types=1);

namespace Felkyo\Core;

/**
 * Writes simple log messages to a file and to the platform's log stream.
 *
 * @package Felkyo\Core
 *
 * WHAT THIS CLASS IS: a very small logger. When something notable happens (a
 * handled error, an important event), the app calls one of these methods and a
 * timestamped line is recorded — in a file in the /logs folder for local work,
 * and via PHP's error log, which is where a hosting platform picks it up. See
 * write() for why both matter.
 *
 * WHY WE WROTE OUR OWN INSTEAD OF ADDING A LIBRARY: our needs are tiny — append
 * a line to a file — and CLAUDE.md (section 11) tells us to prefer a little of
 * our own clear code over a dependency we barely use. If logging needs ever grow
 * a lot (log levels, rotation, sending to a service), a standard library like
 * Monolog would be the moment to reconsider.
 *
 * HOW THIS FITS THE BIGGER PICTURE: any part of the app that needs to record
 * something receives a FileLogger and calls info() or error(). The log file is
 * ignored by Git (it is per-machine runtime output).
 */
final class FileLogger
{
    /**
     * @param string $logFilePath Absolute path to the file lines are appended to.
     */
    public function __construct(private string $logFilePath)
    {
    }

    /**
     * Record normal, expected events (e.g. "seed script finished").
     */
    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    /**
     * Record problems we noticed and handled (e.g. a caught exception).
     */
    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    /**
     * Record one message in BOTH places that can usefully receive it.
     *
     * We build a line like "[2026-07-01 14:03:22] ERROR: message" so logs are
     * easy to scan by eye, then write it twice:
     *
     * 1. To the log file, which is what you read while developing on your own
     *    machine. LOCK_EX asks PHP to lock the file during the write so two
     *    simultaneous requests cannot interleave and corrupt a line.
     *
     * 2. To PHP's error log via error_log(), which on a hosting platform goes to
     *    the container's output — the log stream you actually read in Railway.
     *
     * WHY BOTH, AND WHY THIS MATTERS: on a server, the log FILE lives inside a
     * container. It is invisible from the outside and it disappears when the
     * container restarts. A message written only there is, for practical
     * purposes, a message nobody will ever read. That is not hypothetical: during
     * the first deployment an error was diagnosed entirely from the platform's log
     * stream, because the file was unreachable.
     *
     * The file write is deliberately allowed to fail quietly (the @). If the logs
     * folder is not writable, that must not be allowed to break the actual
     * request — a logger that takes the site down is worse than no logger. The
     * message still reaches error_log() either way.
     */
    private function write(string $level, string $message): void
    {
        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $level, $message);

        @file_put_contents($this->logFilePath, $line, FILE_APPEND | LOCK_EX);
        error_log(sprintf('Felkyo %s: %s', $level, $message));
    }
}
