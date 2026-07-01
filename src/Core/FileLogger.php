<?php

declare(strict_types=1);

namespace Felkyo\Core;

/**
 * Writes simple log messages to a file.
 *
 * @package Felkyo\Core
 *
 * WHAT THIS CLASS IS: a very small logger. When something notable happens (a
 * handled error, an important event), the app calls one of these methods and a
 * timestamped line is appended to a log file in the /logs folder.
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
     * Append one formatted line to the log file.
     *
     * We build a line like "[2026-07-01 14:03:22] ERROR: message" so logs are
     * easy to scan by eye. LOCK_EX asks PHP to lock the file during the write so
     * two simultaneous requests cannot interleave and corrupt a line.
     */
    private function write(string $level, string $message): void
    {
        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $level, $message);
        file_put_contents($this->logFilePath, $line, FILE_APPEND | LOCK_EX);
    }
}
