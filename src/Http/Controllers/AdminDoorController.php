<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\AdminGate;
use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\SecondFactorRepository;
use Felkyo\Admin\Totp;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The panel door: re-confirm the password (and 6-digit code) to enter.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHY A DOOR AT ALL, when you are already logged in: an admin session is
 * worth the whole site, so being logged in as a player is not enough to act
 * as staff. The door re-proves, recently, that the person at the keyboard is
 * the account's owner — and its confirmation goes stale after a configured
 * idle time (AdminGate), so a panel tab forgotten on a shared or lost device
 * closes itself.
 *
 * The door has two shapes:
 *  - Enrolled: password + the current code from the authenticator app (or a
 *    one-time recovery code if the phone is lost).
 *  - Not yet enrolled: password only, which opens ONLY the enrolment screen —
 *    there is no way into the panel proper without a second factor.
 *
 * Wired through AdminGate::protectDoorway (staff only, but no freshness
 * check — the door cannot stand behind itself).
 */
final class AdminDoorController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private Totp $totp,
        private SecondFactorRepository $secondFactors,
        private AuditLogRepository $auditLog,
        private AdminGate $gate,
        private RateLimiter $rateLimiter,
        private array $doorRateLimit,
    ) {
    }

    /**
     * Show the door. If it was passed moments ago, go straight in — asking
     * for a password that was just given is noise, not security.
     */
    public function show(Request $request): Response
    {
        $user = $this->currentUser();

        if ($this->gate->isDoorFreshFor($user->id)) {
            return Response::redirect('/admin');
        }

        return $this->renderDoor($user);
    }

    /**
     * Try to pass the door.
     */
    public function submit(Request $request): Response
    {
        $user = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return $this->renderDoor($user, ['That took too long — please try again.'], 400);
        }

        // The brake on guessing. Keyed on the USER AND the IP together: an
        // attacker who knows the username must not get five guesses from
        // every address, and one shared office IP must not lock out a
        // colleague (see config for the numbers).
        $identifier = 'user:' . $user->id . '|' . $request->clientIp();
        if (!$this->rateLimiter->isAllowed(
            'admin_door',
            $identifier,
            $this->doorRateLimit['max_attempts'],
            $this->doorRateLimit['window_seconds']
        )) {
            return $this->renderDoor($user, ['Too many tries. Wait a while, then try again.'], 429);
        }

        // 1. The password. This is their own logged-in account, so saying
        // plainly that the password was wrong gives nothing away.
        if (!$this->passwordHasher->verify($request->input('password'), $user->passwordHash)) {
            $this->rateLimiter->record('admin_door', $identifier);

            return $this->renderDoor($user, ['That password isn\'t right.'], 401);
        }

        // 2. The second factor — unless they have none yet, in which case the
        // password opens only the enrolment screen.
        $secret = $this->secondFactors->findSecret($user->id);
        if ($secret === null) {
            $this->gate->markPasswordOkFor($user->id);

            return Response::redirect('/admin/enrol');
        }

        $code = trim($request->input('code'));
        $usedRecoveryCode = false;

        if (!$this->totp->verify($secret, $code, time())) {
            // Not the app's code — maybe a recovery code (lost phone).
            // spendRecoveryCode marks it used atomically, so each opens the
            // door exactly once.
            $usedRecoveryCode = $this->secondFactors->spendRecoveryCode($user->id, $code);

            if (!$usedRecoveryCode) {
                $this->rateLimiter->record('admin_door', $identifier);

                return $this->renderDoor(
                    $user,
                    ['That code isn\'t right. Use the current code from your authenticator app, or one of your recovery codes.'],
                    401
                );
            }
        }

        // 3. In. A fresh session id (so nothing planted before the door
        // carries across it), the freshness stamp, and the audit trail.
        $this->session->regenerateId();
        $this->gate->confirmDoorFor($user->id);

        $this->auditLog->record(new AuditEntry(
            actorUserId: $user->id,
            action: AuditAction::PanelEntered,
            ip: $request->clientIp(),
        ));

        if ($usedRecoveryCode) {
            $this->auditLog->record(new AuditEntry(
                actorUserId: $user->id,
                action: AuditAction::RecoveryCodeUsed,
                ip: $request->clientIp(),
            ));

            // Spent codes the person did not spend themselves are the alarm
            // bell — so say the count out loud every time one is used.
            $remaining = $this->secondFactors->countUnusedRecoveryCodes($user->id);
            $this->session->flash(sprintf(
                'You used a recovery code — %d left. If you didn\'t type it yourself just now, tell the owner immediately.',
                $remaining
            ));
        }

        return Response::redirect($this->gate->takeReturnPath());
    }

    /**
     * The logged-in staff user. The gate has already established there is
     * one; this re-read is one indexed lookup, and it is what carries the
     * password hash the door checks against.
     */
    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            // Cannot happen behind the gate; fail loudly rather than guess.
            throw new \RuntimeException('The panel door was reached without a logged-in user.');
        }

        return $user;
    }

    /**
     * Render the door page. It shows a code field only for enrolled accounts
     * — asking for a code nobody has yet would be a riddle, not a form.
     *
     * @param string[] $errors
     */
    private function renderDoor(User $user, array $errors = [], int $statusCode = 200): Response
    {
        return Response::html(
            $this->templates->render('pages/admin-door', [
                'errors' => $errors,
                'enrolled' => $this->secondFactors->findSecret($user->id) !== null,
            ]),
            $statusCode
        );
    }
}
