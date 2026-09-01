<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\AuditLogRepository;
use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Moderation\ModerationRepository;
use Felkyo\Moderation\ModerationService;
use Felkyo\Moderation\NoticeRepository;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Account history on one page, and the account-level actions (M2.7).
 *
 * @package Felkyo\Http\Controllers
 *
 * The pattern view: everything about one account together — name, about
 * text, creatures with their names and bios, guestbooks they signed,
 * reports about them AND by them, notices they were sent, and every
 * moderation action taken on them. Judging reports one at a time is how
 * a repeat offender slips through; this page is the antidote, and the
 * queue links here from every row.
 *
 * The actions here take the account itself as their target (warn, mute,
 * suspend, and the two lifts). Actions that answer a specific REPORT
 * live on the queue (AdminReportsController), where the target derives
 * from the report server-side.
 */
final class AdminAccountController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ProfileRepository $profiles,
        private CreatureRepository $creatures,
        private ModerationService $moderation,
        private ModerationRepository $moderationState,
        private NoticeRepository $notices,
        private AuditLogRepository $auditLog,
        private RateLimiter $rateLimiter,
        private array $actionRateLimit,
        private array $moderationRules,
    ) {
    }

    /** @param array{id: string} $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $userId = (int) ($parameters['id'] ?? 0);
        $profile = $this->profiles->findById($userId);
        if ($profile === null) {
            $this->session->flash('There is no account with that number.');

            return Response::redirect('/admin/reports');
        }

        return Response::html($this->templates->render('pages/admin-account', [
            'profile' => $profile,
            'state' => $this->moderationState->stateFor($userId),
            'creatures' => $this->creatures->findByOwner($userId),
            'signedGuestbooks' => $this->moderationState->guestbookEntriesBy($userId),
            'reportsAbout' => $this->moderationState->reportsAbout($userId),
            'reporterRecord' => $this->moderationState->reporterRecord($userId),
            'noticesSent' => $this->notices->allFor($userId),
            'actionsTaken' => $this->auditLog->aboutUser($userId),
            'timeoutDays' => $this->moderationRules['timeout_days'],
        ]));
    }

    /** @param array{id: string} $parameters */
    public function warn(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $userId) use ($request) {
            return $this->moderation->warn($actor, $userId, null, $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function mute(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $userId) use ($request) {
            return $this->moderation->mute(
                $actor, $userId, (int) $request->input('days', '0'), null, $request->clientIp()
            );
        });
    }

    /** @param array{id: string} $parameters */
    public function suspend(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $userId) use ($request) {
            return $this->moderation->suspend(
                $actor, $userId, (int) $request->input('days', '0'), null, $request->clientIp()
            );
        });
    }

    /** @param array{id: string} $parameters */
    public function unmute(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $userId) use ($request) {
            return $this->moderation->unmute($actor, $userId, $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function unsuspend(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $userId) use ($request) {
            return $this->moderation->unsuspend($actor, $userId, $request->clientIp());
        });
    }

    /**
     * User, CSRF, rate limit (shared with the queue — one budget for all
     * moderating), the action, flash, back to this account's page.
     */
    private function handle(Request $request, array $parameters, callable $action): Response
    {
        $actor = $this->currentUser();
        $userId = (int) ($parameters['id'] ?? 0);
        $backTo = '/admin/accounts/' . $userId;

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }

        if (!$this->rateLimiter->isAllowed(
            'moderation',
            'user:' . $actor->id,
            $this->actionRateLimit['max_attempts'],
            $this->actionRateLimit['window_seconds']
        )) {
            $this->session->flash('That is a lot of actions for one hour — take a breather.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('moderation', 'user:' . $actor->id);

        $result = $action($actor, $userId);
        $this->session->flash($result->message);

        return Response::redirect($backTo);
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The account page was reached without a logged-in user.');
        }

        return $user;
    }
}
