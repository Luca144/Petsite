<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\Role;
use Felkyo\Admin\RoleAssignmentService;
use Felkyo\Admin\RoleRepository;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The owner's roles screen: who is staff, and handing roles out or back.
 *
 * @package Felkyo\Http\Controllers
 *
 * Web glue only: every rule about WHO may change WHAT lives in
 * RoleAssignmentService (owner-only, the last-owner guard, the audit trail).
 * This controller carries the request in, flashes the service's plain-words
 * answer, and redirects — so the browser's refresh button never repeats a
 * role change (Post/Redirect/Get, like every other form on the site).
 *
 * GRANT AND REVOKE ARE SEPARATE ROUTES, not one route with a flag — the
 * difference between "hand someone a key" and "take it back" is never a
 * value the browser sends (the same house rule as sell vs discard).
 */
final class AdminRolesController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private RoleRepository $roles,
        private RoleAssignmentService $assignments,
        private RateLimiter $rateLimiter,
        private array $roleChangeRateLimit,
    ) {
    }

    /**
     * The screen: everyone who holds a role, and the grant form.
     */
    public function show(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-roles', [
            'assignments' => $this->roles->allAssignments(),
            'allRoles' => Role::cases(),
        ]));
    }

    /**
     * Hand the named user a role.
     */
    public function grant(Request $request): Response
    {
        return $this->applyChange($request, function (User $actor, string $username, Role $role) use ($request) {
            return $this->assignments->grant($actor, $username, $role, $request->clientIp());
        });
    }

    /**
     * Take a role away from the named user.
     */
    public function revoke(Request $request): Response
    {
        return $this->applyChange($request, function (User $actor, string $username, Role $role) use ($request) {
            return $this->assignments->revoke($actor, $username, $role, $request->clientIp());
        });
    }

    /**
     * The shared shape of both changes: CSRF, rate limit, validate the role
     * name against the allow-list, run the change, flash the answer, return
     * to the screen. Only the middle step differs.
     */
    private function applyChange(Request $request, callable $change): Response
    {
        $actor = $this->users->findById((int) $this->session->get('user_id'));
        if ($actor === null) {
            // Cannot happen behind the gate; fail loudly rather than guess.
            throw new \RuntimeException('The roles screen was reached without a logged-in user.');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/roles');
        }

        // Role changes are rare; a burst of them is an incident, not a
        // workflow. Keyed on the acting owner, not the IP.
        if (!$this->rateLimiter->isAllowed(
            'admin_role_change',
            'user:' . $actor->id,
            $this->roleChangeRateLimit['max_attempts'],
            $this->roleChangeRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of role changes at once. Wait a while, then continue.');

            return Response::redirect('/admin/roles');
        }

        // The role name arrives from the form; only the four known names
        // exist (Role.php). Anything else is refused, not guessed at.
        $role = Role::fromFormValue($request->input('role'));
        if ($role === null) {
            $this->session->flash('That isn\'t one of the four roles.');

            return Response::redirect('/admin/roles');
        }

        $this->rateLimiter->record('admin_role_change', 'user:' . $actor->id);

        $result = $change($actor, $request->input('username'), $role);
        $this->session->flash($result->message);

        return Response::redirect('/admin/roles');
    }
}
