<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Moderation\ModerationRepository;
use Felkyo\Moderation\ModerationService;
use Felkyo\Safety\ReportSubject;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The report queue and its actions (M2.7).
 *
 * @package Felkyo\Http\Controllers
 *
 * The queue is the moderator's inbox: open reports, most urgent first.
 * EVERY ACTION IS ADDRESSED BY REPORT ID, and the target — which player,
 * which creature, which text — is derived from the report ON THE SERVER.
 * The forms post no target ids at all, so a tampered form can't point an
 * action at somebody else: the worst a forged id can do is act on the
 * report it names, which is exactly what the moderator's own screen
 * offers anyway.
 *
 * Account-level actions without a report (warn/mute from the history
 * page) live in AdminAccountController; the two share the service.
 */
final class AdminReportsController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ModerationService $moderation,
        private ModerationRepository $reports,
        private RateLimiter $rateLimiter,
        private array $actionRateLimit,
        private array $moderationRules,
    ) {
    }

    /** The queue itself. */
    public function show(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-reports', [
            'queue' => $this->moderation->queue(),
            'timeoutDays' => $this->moderationRules['timeout_days'],
        ]));
    }

    /** @param array{id: string} $parameters */
    public function dismiss(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, array $report) use ($request) {
            return $this->moderation->dismiss($actor, (int) $report['id'], $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function warn(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, array $report) use ($request) {
            return $this->moderation->warn(
                $actor, (int) $report['about_user_id'], (int) $report['id'], $request->clientIp()
            );
        });
    }

    /**
     * Remove the reported text — which text is derived from the report's
     * own subject (class note). A report about a NAME has no text to
     * remove; the refusal says to use rename instead (golden rule 3).
     *
     * @param array{id: string} $parameters
     */
    public function removeText(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, array $report) use ($request) {
            $reportId = (int) $report['id'];
            $subjectId = (int) $report['subject_id'];

            return match (ReportSubject::tryFrom((string) $report['subject_type'])) {
                ReportSubject::CreatureBio =>
                    $this->moderation->removeCreatureBio($actor, $subjectId, $reportId, $request->clientIp()),
                ReportSubject::ProfileAbout =>
                    $this->moderation->removeProfileAbout($actor, $subjectId, $reportId, $request->clientIp()),
                default => ContentEditResult::refused(
                    'This report is about a name — "give a neutral name" is the action for it.'
                ),
            };
        });
    }

    /**
     * Give the reported name a neutral default — player or creature,
     * derived from the report's subject.
     *
     * @param array{id: string} $parameters
     */
    public function rename(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, array $report) use ($request) {
            $reportId = (int) $report['id'];
            $subjectId = (int) $report['subject_id'];

            return match (ReportSubject::tryFrom((string) $report['subject_type'])) {
                ReportSubject::CreatureName =>
                    $this->moderation->renameCreature($actor, $subjectId, $reportId, $request->clientIp()),
                ReportSubject::Username =>
                    $this->moderation->renamePlayer($actor, $subjectId, $reportId, $request->clientIp()),
                default => ContentEditResult::refused(
                    'This report is about written text — "remove the text" is the action for it.'
                ),
            };
        });
    }

    /** @param array{id: string} $parameters */
    public function mute(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, array $report) use ($request) {
            return $this->moderation->mute(
                $actor, (int) $report['about_user_id'],
                (int) $request->input('days', '0'), (int) $report['id'], $request->clientIp()
            );
        });
    }

    /** @param array{id: string} $parameters */
    public function suspend(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, array $report) use ($request) {
            return $this->moderation->suspend(
                $actor, (int) $report['about_user_id'],
                (int) $request->input('days', '0'), (int) $report['id'], $request->clientIp()
            );
        });
    }

    /**
     * User, CSRF, rate limit, load the report, run the action, flash,
     * back to the queue — the panel's usual shape, moderation-flavoured.
     */
    private function handle(Request $request, array $parameters, callable $action): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/reports');
        }

        if (!$this->rateLimiter->isAllowed(
            'moderation',
            'user:' . $actor->id,
            $this->actionRateLimit['max_attempts'],
            $this->actionRateLimit['window_seconds']
        )) {
            $this->session->flash('That is a lot of actions for one hour — the queue will keep. Take a breather.');

            return Response::redirect('/admin/reports');
        }
        $this->rateLimiter->record('moderation', 'user:' . $actor->id);

        $report = $this->reports->findReport((int) ($parameters['id'] ?? 0));
        if ($report === null) {
            $this->session->flash('That report is not here.');

            return Response::redirect('/admin/reports');
        }

        $result = $action($actor, $report);
        $this->session->flash($result->message);

        return Response::redirect('/admin/reports');
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The report queue was reached without a logged-in user.');
        }

        return $user;
    }
}
