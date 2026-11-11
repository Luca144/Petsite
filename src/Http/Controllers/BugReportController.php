<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Auth\Session;
use Felkyo\Feedback\BugReportRepository;
use Felkyo\Feedback\BugReportService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * "Something broken?" — players telling us about bugs, and the panel's list.
 *
 *   GET  /bug-report?from=/page   the form, remembering which page it's about
 *   POST /bug-report              send it; back to the page you were on
 *   GET  /admin/bugs?status=open  the panel's list (coder role; owner passes)
 *   POST /admin/bugs/{id}/status  mark one fixed / closed / open again
 *
 * @package Felkyo\Http\Controllers
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md §7):
 * 1. Who may? Sending: any logged-in player (checked here — a guest is sent
 *    to log in). Reading and ticking off: the coder role, enforced by the
 *    AdminGate around the /admin routes in index.php.
 * 2. Worst case? Flooding the list (rate limit 'bug_report' per account);
 *    hostile text (only ever shown escaped, to staff); a hostile link as the
 *    page address (BugReportService::sitePath only keeps paths on this site).
 * 3. Tests: smoke test sends one through the real form.
 */
final class BugReportController
{
    private const LIST_LIMIT = 100;

    /** @param array{max_attempts: int, window_seconds: int} $rateLimit */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private BugReportService $service,
        private BugReportRepository $reports,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
        private ?AuditLogRepository $auditLog = null,
    ) {
    }

    public function form(Request $request): Response
    {
        if (!is_int($this->session->get('user_id'))) {
            return Response::redirect('/login');
        }

        return Response::html($this->templates->render('pages/bug-report', [
            'from' => BugReportService::sitePath($request->query('from', '/')),
            'description' => '',
        ]));
    }

    public function send(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }
        $from = BugReportService::sitePath($request->input('from', '/'));
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/bug-report?from=' . rawurlencode($from));
        }

        $bucket = 'user:' . $userId;
        if (!$this->rateLimiter->isAllowed('bug_report', $bucket, $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('Thank you for all the reports! Please give us a little while before sending more.');

            return Response::redirect($from);
        }

        $problem = $this->service->send($userId, [
            'path' => $from,
            'browser' => $request->userAgent(),
            'width' => $request->input('screen_width'),
            'description' => $request->input('description'),
        ]);
        if ($problem !== null) {
            // Show the form again WITH their words, so nothing they typed is
            // lost. The message goes into the form itself (not the session
            // flash, which would pop up again on the NEXT page too).
            return Response::html($this->templates->render('pages/bug-report', [
                'from' => $from,
                'description' => $request->input('description'),
                'errors' => [$problem],
            ]));
        }
        $this->rateLimiter->record('bug_report', $bucket);
        $this->session->flash('Thank you! Your bug report reached us — we\'ll take a look.');

        return Response::redirect($from);
    }

    public function index(Request $request): Response
    {
        $status = $request->query('status', BugReportRepository::STATUS_OPEN);
        if (!in_array($status, BugReportRepository::STATUSES, true)) {
            $status = BugReportRepository::STATUS_OPEN;
        }

        return Response::html($this->templates->render('pages/admin-bugs', [
            'status' => $status,
            'counts' => $this->reports->countsByStatus(),
            'reports' => $this->reports->withStatus($status, self::LIST_LIMIT),
        ]));
    }

    public function setStatus(Request $request, array $parameters): Response
    {
        $userId = $this->session->get('user_id');
        $status = $request->input('status');
        $backTo = '/admin/bugs?status=' . rawurlencode($request->input('back', 'open'));
        if (!is_int($userId) || !$this->csrf->isValid($request->input('_csrf_token'))
            || !in_array($status, BugReportRepository::STATUSES, true)) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }

        $id = (int) ($parameters['id'] ?? 0);
        if (!$this->reports->setStatus($id, $status, $userId)) {
            $this->session->flash('That report isn\'t there any more.');

            return Response::redirect($backTo);
        }
        $this->auditLog?->record(new AuditEntry(
            actorUserId: $userId, action: AuditAction::BugReportHandled, subjectType: 'bug_report',
            subjectId: $id, after: ['status' => $status], ip: $request->clientIp(),
        ));
        $this->session->flash(sprintf('Report #%d is now marked %s.', $id, $status));

        return Response::redirect($backTo);
    }
}
