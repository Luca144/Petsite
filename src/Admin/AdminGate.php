<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use Felkyo\Auth\Session;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The one door every admin request passes through.
 *
 * @package Felkyo\Admin
 *
 * WHAT THIS CLASS IS: a wrapper that goes around every admin controller at
 * route-registration time. The front controller never registers an /admin
 * route directly — it registers protect(role, handler), which returns a
 * handler that authorises FIRST and only then runs the real one. That makes
 * "an admin route that forgot its permission check" structurally impossible:
 * there is no other way to register one. (The AdminGateTest matrix walks
 * every admin route with every role to prove it.)
 *
 * THE CHECKS RUN ON EVERY REQUEST, in this order:
 *   1. Logged in at all? No → the login page.
 *   2. Holds ANY staff role? Read FRESH from the database — never from the
 *      session — so a revoked role stops working on the very next request.
 *      No roles → the same 404 any unknown URL gets. The panel does not
 *      confirm its existence to players (that would be a target list).
 *   3. Holds the role THIS screen needs? Owner passes every check ("owner —
 *      everything", build plan M2.1). Wrong role → a plain-worded page
 *      naming which role the screen needs — a colleague, not an intruder.
 *   4. Has the panel door been passed recently? The door (password + code,
 *      AdminDoorController) must be re-passed after a configured idle time,
 *      so a panel tab open on a lost or shared device goes cold on its own.
 *
 * WHY THE DOOR STATE REMEMBERS WHICH USER PASSED IT: the session survives
 * logout-then-login (only user_id changes). If the freshness stamp did not
 * name its user, admin A's still-warm confirmation could open the door for
 * whoever logs in next on the same browser. Stamping the user id closes that.
 */
final class AdminGate
{
    // Session keys, kept as constants so the door controller and this gate
    // can never drift apart on spelling.
    public const SESSION_CONFIRMED = 'admin_confirmed';
    public const SESSION_PASSWORD_OK = 'admin_door_password_ok';
    public const SESSION_RETURN_TO = 'admin_return_to';

    /**
     * @param array $adminConfig The 'admin' block of the security config:
     *                           confirm_max_age_seconds and
     *                           password_ok_max_age_seconds.
     */
    public function __construct(
        private Session $session,
        private UserRepository $users,
        private RoleRepository $roles,
        private Engine $templates,
        private array $adminConfig,
    ) {
    }

    /**
     * Wrap an admin handler in the full set of checks above. $required names
     * the role the screen needs; null means "any staff role" (the panel home,
     * which every staff member may see).
     */
    public function protect(?Role $required, callable $handler): callable
    {
        return function (Request $request, array $parameters) use ($required, $handler): Response {
            $user = $this->staffUser();
            if (!$user instanceof User) {
                return $this->refuseOutsider($user);
            }

            if ($required !== null && !$this->holds($user, $required)) {
                return $this->refuseWrongRole($required);
            }

            if (!$this->isDoorFreshFor($user->id)) {
                // Remember where they were going (GET only — re-driving a
                // POST after the door would repeat an action nobody re-chose),
                // then send them to the door.
                if ($request->method() === 'GET') {
                    $this->session->set(self::SESSION_RETURN_TO, $request->path());
                }

                return Response::redirect('/admin/door');
            }

            return $handler($request, $parameters);
        };
    }

    /**
     * Wrap one of the door's own routes (the door form, enrolment). These
     * check login and staff-ness but NOT door freshness — the door cannot be
     * behind itself.
     */
    public function protectDoorway(callable $handler): callable
    {
        return function (Request $request, array $parameters) use ($handler): Response {
            $user = $this->staffUser();
            if (!$user instanceof User) {
                return $this->refuseOutsider($user);
            }

            return $handler($request, $parameters);
        };
    }

    // ---- What the door controller needs to stamp and read ----

    /**
     * Has THIS user passed the door within the configured window?
     */
    public function isDoorFreshFor(int $userId): bool
    {
        $confirmed = $this->session->get(self::SESSION_CONFIRMED);

        return is_array($confirmed)
            && ($confirmed['user_id'] ?? null) === $userId
            && is_int($confirmed['at'] ?? null)
            && (time() - $confirmed['at']) <= (int) $this->adminConfig['confirm_max_age_seconds'];
    }

    /**
     * Stamp the door as freshly passed for this user. Called by the door
     * controller after password (and code) verified — never anywhere else.
     */
    public function confirmDoorFor(int $userId): void
    {
        $this->session->set(self::SESSION_CONFIRMED, ['user_id' => $userId, 'at' => time()]);
    }

    /**
     * Stamp "password accepted, enrolment pending" — the short-lived half-way
     * state between the door and finishing second-factor setup.
     */
    public function markPasswordOkFor(int $userId): void
    {
        $this->session->set(self::SESSION_PASSWORD_OK, ['user_id' => $userId, 'at' => time()]);
    }

    /**
     * Is the half-way state above still fresh for this user? The window is
     * deliberately shorter than the door's own (password_ok_max_age_seconds):
     * it only needs to span "typed password, now typing a secret into an app".
     */
    public function isPasswordOkFreshFor(int $userId): bool
    {
        $passwordOk = $this->session->get(self::SESSION_PASSWORD_OK);

        return is_array($passwordOk)
            && ($passwordOk['user_id'] ?? null) === $userId
            && is_int($passwordOk['at'] ?? null)
            && (time() - $passwordOk['at']) <= (int) $this->adminConfig['password_ok_max_age_seconds'];
    }

    /**
     * Where to land after the door: the remembered admin page, or the panel
     * home. The remembered value is checked to be an /admin path — the
     * session is server-side, but a redirect target is exactly the kind of
     * value that deserves an allow-list anyway.
     */
    public function takeReturnPath(): string
    {
        $path = $this->session->get(self::SESSION_RETURN_TO);
        $this->session->remove(self::SESSION_RETURN_TO);

        return is_string($path) && str_starts_with($path, '/admin') ? $path : '/admin';
    }

    // ---- The checks themselves ----

    /**
     * The logged-in user IF they hold any staff role. Returns the User, or
     * null (not logged in), or false (logged in but not staff) — the two
     * refusals look different to the visitor, so both facts are needed.
     */
    private function staffUser(): User|null|false
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return null;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return null;
        }

        // Fresh read on every request — the whole point (class docblock).
        return $this->roles->rolesFor($user->id) === [] ? false : $user;
    }

    /**
     * Does this user hold this role? Owner passes everything.
     */
    private function holds(User $user, Role $required): bool
    {
        $held = $this->roles->rolesFor($user->id);

        return in_array($required, $held, true) || in_array(Role::Owner, $held, true);
    }

    /**
     * Refuse someone who is not staff: guests are sent to log in; a logged-in
     * player gets the same 404 as any unknown URL (see class docblock).
     */
    private function refuseOutsider(null|false $who): Response
    {
        if ($who === null) {
            return Response::redirect('/login');
        }

        return Response::html(
            $this->templates->render('pages/not-found', ['message' => 'We couldn\'t find that page.']),
            404
        );
    }

    /**
     * Refuse a staff member whose roles don't include this screen's. Plain
     * words naming the missing role — they are a colleague who clicked the
     * wrong thing, not an intruder (golden rule 3: never a dead end).
     */
    private function refuseWrongRole(Role $required): Response
    {
        return Response::html(
            $this->templates->render('pages/admin-forbidden', ['requiredRole' => $required]),
            403
        );
    }
}
