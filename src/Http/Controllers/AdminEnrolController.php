<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\AdminGate;
use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\SecondFactorRepository;
use Felkyo\Admin\Totp;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;
use PDO;

/**
 * Connecting an authenticator app to a staff account (TOTP enrolment).
 *
 * @package Felkyo\Http\Controllers
 *
 * THE FLOW: the panel door sends an un-enrolled staff member here after their
 * password checks out (a short-lived "password ok" stamp proves that — see
 * AdminGate). This screen shows a fresh secret to type into any authenticator
 * app, and asks for one current code back. The code proving the app really
 * holds the secret is what completes enrolment — a mistyped secret fails
 * HERE, at setup, not at tomorrow's locked door.
 *
 * On success the person is shown their one-time recovery codes EXACTLY ONCE
 * (they are stored hashed, so the site itself cannot show them again), and
 * the door is stamped passed — they just proved password and factor both.
 *
 * WHY THE PENDING SECRET LIVES IN THE SESSION, NOT THE DATABASE: until a code
 * confirms it, the secret is a proposal, not an enrolment. Kept server-side
 * in the session it dies with the session, leaves no half-enrolled rows, and
 * never travels except printed once on this page.
 */
final class AdminEnrolController
{
    // The session key holding the not-yet-confirmed secret.
    private const SESSION_PENDING_SECRET = 'admin_enrol_pending_secret';

    // How many recovery codes an enrolment hands out. Enough to survive a
    // few lost phones; few enough to fit on one note.
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private Totp $totp,
        private SecondFactorRepository $secondFactors,
        private AuditLogRepository $auditLog,
        private AdminGate $gate,
        private PDO $connection,
    ) {
    }

    /**
     * Show the enrolment screen: the secret, the otpauth address, and the
     * "type one code back" form.
     */
    public function show(Request $request): Response
    {
        $user = $this->currentUser();

        // Already enrolled? Then the door is the right place, not this one.
        if ($this->secondFactors->findSecret($user->id) !== null) {
            return Response::redirect('/admin/door');
        }

        // No fresh password confirmation → back to the door. This screen
        // hands out a secret that can open the panel; the password check in
        // front of it is not optional.
        if (!$this->gate->isPasswordOkFreshFor($user->id)) {
            return Response::redirect('/admin/door');
        }

        return $this->renderEnrolment($user);
    }

    /**
     * Check the first code and complete the enrolment.
     */
    public function submit(Request $request): Response
    {
        $user = $this->currentUser();

        if ($this->secondFactors->findSecret($user->id) !== null) {
            return Response::redirect('/admin/door');
        }

        if (!$this->gate->isPasswordOkFreshFor($user->id)) {
            return Response::redirect('/admin/door');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return $this->renderEnrolment($user, ['That took too long — please try again.'], 400);
        }

        $pendingSecret = $this->session->get(self::SESSION_PENDING_SECRET);
        if (!is_string($pendingSecret) || $pendingSecret === '') {
            return $this->renderEnrolment($user, ['Something went sideways — here is a fresh secret to set up with.'], 400);
        }

        if (!$this->totp->verify($pendingSecret, $request->input('code'), time())) {
            return $this->renderEnrolment(
                $user,
                ['That code doesn\'t match. Check the secret was typed into the app exactly, then try the app\'s current code.'],
                401
            );
        }

        // The app provably holds the secret — make it real. Secret, recovery
        // codes and the audit entry land together or not at all.
        $recoveryCodes = $this->makeRecoveryCodes();

        $this->connection->beginTransaction();
        try {
            $this->secondFactors->saveSecret($user->id, $pendingSecret);
            $this->secondFactors->replaceRecoveryCodes($user->id, $recoveryCodes);

            $this->auditLog->record(new AuditEntry(
                actorUserId: $user->id,
                action: AuditAction::SecondFactorEnrolled,
                subjectType: 'user',
                subjectId: $user->id,
                ip: $request->clientIp(),
            ));

            // Finishing enrolment also opens the panel (the person just
            // proved password AND factor), so the log records the entry the
            // same way the door would — "who was in the panel, and when"
            // must have no unrecorded first visit.
            $this->auditLog->record(new AuditEntry(
                actorUserId: $user->id,
                action: AuditAction::PanelEntered,
                ip: $request->clientIp(),
            ));

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        // They just proved password AND factor, so the door is passed — and
        // the pending state is finished with.
        $this->session->remove(self::SESSION_PENDING_SECRET);
        $this->session->remove(AdminGate::SESSION_PASSWORD_OK);
        $this->session->regenerateId();
        $this->gate->confirmDoorFor($user->id);

        // The one and only showing of the recovery codes (class docblock).
        return Response::html($this->templates->render('pages/admin-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
        ]));
    }

    /**
     * Render the enrolment page, minting a pending secret if none is waiting.
     * The same secret is kept across re-renders (a wrong first code must not
     * silently swap the secret out from under the app that already has it).
     *
     * @param string[] $errors
     */
    private function renderEnrolment(User $user, array $errors = [], int $statusCode = 200): Response
    {
        $pendingSecret = $this->session->get(self::SESSION_PENDING_SECRET);
        if (!is_string($pendingSecret) || $pendingSecret === '') {
            $pendingSecret = $this->totp->generateSecret();
            $this->session->set(self::SESSION_PENDING_SECRET, $pendingSecret);
        }

        return Response::html(
            $this->templates->render('pages/admin-enrol', [
                'errors' => $errors,
                'secret' => $pendingSecret,
                'provisioningUri' => $this->totp->provisioningUri($pendingSecret, $user->username),
            ]),
            $statusCode
        );
    }

    /**
     * Mint the one-time recovery codes: readable blocks like "A3F9-2C71",
     * from bytes that were random, not chosen. Shown once, stored hashed.
     *
     * @return string[]
     */
    private function makeRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($hex, 0, 4) . '-' . substr($hex, 4, 4);
        }

        return $codes;
    }

    /**
     * The logged-in staff user (the gate has already established there is one).
     */
    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The enrolment screen was reached without a logged-in user.');
        }

        return $user;
    }
}
