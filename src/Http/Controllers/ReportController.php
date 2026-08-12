<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Safety\ReportReason;
use Felkyo\Safety\ReportService;
use Felkyo\Safety\ReportSubject;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * The report button: choosing a reason, and filing it.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHO MAY REPORT: any logged-in player, about anything they can see. Logged-out
 * visitors may not — a report from nobody cannot be followed up, cannot be
 * counted, and cannot be weighed against the reporter's history, which is how a
 * small team tells a real problem from a grudge. It would also be a button
 * anybody could press ten thousand times.
 *
 * THE FORM IS A PAGE, NOT A POP-UP. Reporting is often done by somebody upset, on
 * a phone, possibly in a hurry. A full page with large tappable choices works
 * without JavaScript, works with a screen reader, and cannot be dismissed by a
 * mis-tap. It also means the back button behaves the way people expect.
 *
 * THERE IS NO FREE-TEXT BOX, and that is a safety decision rather than a
 * simplification — see ReportReason for why.
 */
final class ReportController
{
    /**
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private ReportService $reports,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    /**
     * Show the list of reasons.
     *
     * @param array<string, string> $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        if (!is_int($this->session->get('user_id'))) {
            // Sent to log in rather than told "you must be logged in to report",
            // which reads as an obstacle at the worst possible moment.
            return Response::redirect('/login');
        }

        $subject = ReportSubject::fromFormValue((string) ($parameters['subject'] ?? ''));

        if ($subject === null) {
            return Response::redirect('/');
        }

        return Response::html($this->templates->render('pages/report', [
            'subject' => $subject,
            'subjectId' => (int) ($parameters['id'] ?? 0),
            'reasons' => ReportReason::inOfferedOrder(),
        ]));
    }

    /**
     * File the report and say thank you.
     */
    public function submit(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect('/');
        }

        $subject = ReportSubject::fromFormValue($request->input('subject'));
        $reason = ReportReason::fromFormValue($request->input('reason'));
        $subjectId = (int) $request->input('subject_id');

        // Either value being unknown means the form was not one of ours. Send them
        // home quietly rather than explaining what was wrong with their forgery.
        if ($subject === null || $reason === null || $subjectId <= 0) {
            return Response::redirect('/');
        }

        // Rate limited like every state-changing action. The ceiling is generous:
        // somebody genuinely upset may report several things in a row, and being
        // told to slow down at that moment would be its own small harm.
        if (!$this->rateLimiter->isAllowed('report', $request->clientIp(), $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('You have sent us a lot just now — please give us a moment to read them.');
            return Response::redirect('/');
        }
        $this->rateLimiter->record('report', $request->clientIp());

        $result = $this->reports->report($userId, $subject, $subjectId, $reason);

        return Response::html($this->templates->render('pages/report-received', [
            'message' => $result->message(),
            'wasReceived' => $result->wasReceived(),
        ]));
    }
}
