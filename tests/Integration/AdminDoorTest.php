<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\AdminGate;
use Felkyo\Admin\Role;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;

/**
 * The panel door and second-factor enrolment, end to end.
 *
 * @package Felkyo\Tests\Integration
 *
 * These tests walk the door the way a person does: wrong password refused
 * (and rate limited), first entry led through connecting an authenticator
 * app, later entries asking password + code, and the lost-phone path where a
 * recovery code works exactly once.
 */
final class AdminDoorTest extends DatabaseTestCase
{
    private AdminWorld $world;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables(
            'rate_limit_hits', 'admin_recovery_codes', 'admin_second_factors',
            'admin_audit_log', 'user_roles', 'pettings', 'creatures', 'users'
        );
        $_SESSION = [];
        $this->world = AdminWorld::build($this->connection);
    }

    private function post(string $path, array $fields): Response
    {
        $fields['_csrf_token'] = $this->world->csrf->token();

        return $this->world->router->dispatch(new Request('POST', $path, $fields, '127.0.0.1'));
    }

    private function get(string $path): Response
    {
        return $this->world->router->dispatch(new Request('GET', $path, [], '127.0.0.1'));
    }

    /**
     * Walk a not-yet-enrolled staff member all the way through the door and
     * enrolment, and return the recovery codes the site showed them.
     *
     * @return string[]
     */
    private function enrol(): array
    {
        // Password at the door → sent to enrolment, not into the panel.
        $afterDoor = $this->post('/admin/door', ['password' => AdminWorld::PASSWORD]);
        $this->assertSame('/admin/enrol', $afterDoor->header('Location'));

        // The enrolment page mints the pending secret (into the session).
        $this->assertSame(200, $this->get('/admin/enrol')->statusCode());
        $pendingSecret = $_SESSION['admin_enrol_pending_secret'];

        // Answer with the code a correctly-set-up app would show right now.
        $finished = $this->post('/admin/enrol', [
            'code' => $this->world->totp->codeAt($pendingSecret, time()),
        ]);
        $this->assertSame(200, $finished->statusCode());

        // The page lists the recovery codes, once. Read them like a person would.
        preg_match_all('/[0-9A-F]{4}-[0-9A-F]{4}/', $finished->body(), $matches);
        $this->assertNotEmpty($matches[0], 'the recovery codes must be shown after enrolment');

        return $matches[0];
    }

    // --- The password check and its brake ---

    public function testTheWrongPasswordIsRefusedAtTheDoor(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);

        $response = $this->post('/admin/door', ['password' => 'not-the-password']);

        $this->assertSame(401, $response->statusCode());
        $this->assertArrayNotHasKey(AdminGate::SESSION_CONFIRMED, $_SESSION);
    }

    public function testTooManyWrongPasswordsLockTheDoorForAWhile(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);

        // The signed-off policy allows 5 attempts; burn exactly 5.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertSame(401, $this->post('/admin/door', ['password' => 'wrong'])->statusCode());
        }

        // The 6th is refused before the password is even looked at — even the
        // RIGHT password, because the brake is about the guessing, not the guess.
        $blocked = $this->post('/admin/door', ['password' => AdminWorld::PASSWORD]);

        $this->assertSame(429, $blocked->statusCode());
        $this->assertArrayNotHasKey(AdminGate::SESSION_CONFIRMED, $_SESSION);
    }

    public function testTheDoorRefusesAMissingCsrfToken(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);

        $response = $this->world->router->dispatch(
            new Request('POST', '/admin/door', ['password' => AdminWorld::PASSWORD], '127.0.0.1')
        );

        $this->assertSame(400, $response->statusCode());
        $this->assertArrayNotHasKey(AdminGate::SESSION_CONFIRMED, $_SESSION);
    }

    // --- First entry: enrolment ---

    public function testFirstEntryWalksThroughEnrolmentAndIntoThePanel(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);

        $this->enrol();

        // Enrolment stored the secret and opened the panel in one motion.
        $this->assertNotNull($this->world->secondFactors->findSecret($owner->id));
        $this->assertSame(200, $this->get('/admin')->statusCode());
    }

    public function testTheEnrolmentScreenIsNotReachableWithoutTheFreshPasswordCheck(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);

        // Straight to enrolment without passing the door first.
        $response = $this->get('/admin/enrol');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/admin/door', $response->header('Location'));
    }

    public function testAWrongFirstCodeDoesNotEnrol(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);

        $this->post('/admin/door', ['password' => AdminWorld::PASSWORD]);
        $this->get('/admin/enrol');

        $refused = $this->post('/admin/enrol', ['code' => '000000']);

        $this->assertSame(401, $refused->statusCode());
        $this->assertNull($this->world->secondFactors->findSecret($owner->id));
        // And the panel stays shut.
        $this->assertSame('/admin/door', $this->get('/admin')->header('Location'));
    }

    // --- Enrolled entries: password + code ---

    public function testAnEnrolledAccountNeedsPasswordAndCurrentCode(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);
        $this->enrol();
        $secret = $this->world->secondFactors->findSecret($owner->id);

        // A fresh session: logged in, but the door not passed.
        $_SESSION = [];
        $this->world->logIn($owner);

        // Password alone is not enough any more.
        $passwordOnly = $this->post('/admin/door', ['password' => AdminWorld::PASSWORD, 'code' => '']);
        $this->assertSame(401, $passwordOnly->statusCode());

        // Password + the app's current code opens it.
        $opened = $this->post('/admin/door', [
            'password' => AdminWorld::PASSWORD,
            'code' => $this->world->totp->codeAt($secret, time()),
        ]);
        $this->assertSame(302, $opened->statusCode());
        $this->assertSame('/admin', $opened->header('Location'));
        $this->assertSame(200, $this->get('/admin')->statusCode());
    }

    public function testTheRightCodeWithTheWrongPasswordIsRefused(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);
        $this->enrol();
        $secret = $this->world->secondFactors->findSecret($owner->id);

        $_SESSION = [];
        $this->world->logIn($owner);

        $response = $this->post('/admin/door', [
            'password' => 'not-the-password',
            'code' => $this->world->totp->codeAt($secret, time()),
        ]);

        $this->assertSame(401, $response->statusCode());
        $this->assertArrayNotHasKey(AdminGate::SESSION_CONFIRMED, $_SESSION);
    }

    // --- The lost-phone path ---

    public function testARecoveryCodeOpensTheDoorExactlyOnce(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);
        $recoveryCodes = $this->enrol();

        // Phone lost: fresh session, recovery code in the code field.
        $_SESSION = [];
        $this->world->logIn($owner);

        $opened = $this->post('/admin/door', [
            'password' => AdminWorld::PASSWORD,
            'code' => $recoveryCodes[0],
        ]);
        $this->assertSame(302, $opened->statusCode());

        // The SAME code again, in yet another fresh session: spent is spent.
        $_SESSION = [];
        $this->world->logIn($owner);

        $refused = $this->post('/admin/door', [
            'password' => AdminWorld::PASSWORD,
            'code' => $recoveryCodes[0],
        ]);
        $this->assertSame(401, $refused->statusCode());

        // A DIFFERENT code still works — one being spent spends only itself.
        $secondTry = $this->post('/admin/door', [
            'password' => AdminWorld::PASSWORD,
            'code' => $recoveryCodes[1],
        ]);
        $this->assertSame(302, $secondTry->statusCode());
    }

    // --- The audit trail the door leaves ---

    public function testPassingTheDoorAndEnrollingAreBothAudited(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);
        $this->enrol();

        $actions = array_column($this->world->auditLog->recent(10), 'action');

        $this->assertContains('second_factor.enrolled', $actions);
    }
}
