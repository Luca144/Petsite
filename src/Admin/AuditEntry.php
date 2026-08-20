<?php

declare(strict_types=1);

namespace Felkyo\Admin;

/**
 * One thing that happened, ready to be written to the audit log.
 *
 * @package Felkyo\Admin
 *
 * WHY THIS OBJECT EXISTS: an audit record has seven parts (who, what, what it
 * happened to, before, after, from where), and a method taking seven loose
 * parameters is unreadable at the call site (CLAUDE.md section 5 caps
 * parameters at four). Named constructor arguments make every call spell out
 * what each value is:
 *
 *   new AuditEntry(
 *       actorUserId: $owner->id,
 *       action: AuditAction::RoleGranted,
 *       subjectType: 'user',
 *       subjectId: $target->id,
 *       before: ['roles' => ['artist']],
 *       after: ['roles' => ['artist', 'moderator']],
 *       ip: $request->clientIp(),
 *   )
 */
final class AuditEntry
{
    /**
     * @param int|null    $actorUserId Who did it; null means a CLI script.
     * @param AuditAction $action      What happened, from the allow-list.
     * @param string|null $subjectType What KIND of thing it happened to
     *                                 (e.g. 'user'), or null for actions
     *                                 with no subject.
     * @param int|null    $subjectId   Which one, within that kind's table.
     * @param array|null  $before      The relevant values before the action.
     * @param array|null  $after      The relevant values after it.
     * @param string      $ip          Where the request came from ('cli' for
     *                                 the command-line scripts).
     */
    public function __construct(
        public readonly ?int $actorUserId,
        public readonly AuditAction $action,
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
        public readonly ?array $before = null,
        public readonly ?array $after = null,
        public readonly string $ip = 'cli',
    ) {
    }
}
