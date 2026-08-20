<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * The audit page: everything staff have done in the panel, newest first.
 *
 * @package Felkyo\Http\Controllers
 *
 * Read-only, owner-only. This page existing is part of the audit log's job:
 * a log nobody can comfortably read is a log nobody reads, and the log is how
 * a misused admin account gets NOTICED (the spend of a recovery code, a role
 * grant at 3am). The repository has no delete, and this controller adds no
 * action — nothing on this page changes anything.
 */
final class AdminAuditController
{
    // One screenful of history. When the log outgrows this, add paging —
    // for two part-time humans, "the last hundred things" is the question.
    private const ENTRIES_SHOWN = 100;

    public function __construct(
        private Engine $templates,
        private AuditLogRepository $auditLog,
    ) {
    }

    public function show(Request $request): Response
    {
        $entries = [];
        foreach ($this->auditLog->recent(self::ENTRIES_SHOWN) as $row) {
            // Translate the stored action name into its plain-words label
            // here, so the template only prints. An action name written by a
            // FUTURE version of the code (after a rollback, say) shows as its
            // raw name rather than crashing the one page that explains it.
            $action = AuditAction::tryFrom((string) $row['action']);

            $entries[] = [
                'who' => $row['actor_username'] ?? 'the command line',
                'what' => $action?->label() ?? (string) $row['action'],
                'subjectType' => $row['subject_type'],
                'subjectId' => $row['subject_id'],
                'before' => $row['detail_before'],
                'after' => $row['detail_after'],
                'ip' => $row['ip'],
                'when' => $row['created_at'],
            ];
        }

        return Response::html($this->templates->render('pages/admin-audit', [
            'entries' => $entries,
        ]));
    }
}
