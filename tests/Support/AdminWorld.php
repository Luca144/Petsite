<?php

declare(strict_types=1);

namespace Felkyo\Tests\Support;

use Felkyo\Admin\AdminGate;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\Role;
use Felkyo\Admin\RoleAssignmentService;
use Felkyo\Admin\RoleRepository;
use Felkyo\Admin\SecondFactorRepository;
use Felkyo\Admin\Totp;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\Session;
use Felkyo\Http\Controllers\AdminAuditController;
use Felkyo\Http\Controllers\AdminDoorController;
use Felkyo\Http\Controllers\AdminEnrolController;
use Felkyo\Http\Controllers\AdminHomeController;
use Felkyo\Http\Controllers\AdminRolesController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;
use PDO;

/**
 * Builds the whole admin panel for a test, wired exactly as the front
 * controller wires it.
 *
 * @package Felkyo\Tests\Support
 *
 * WHY THIS EXISTS: the panel's tests (the gate matrix, the door, enrolment)
 * all need the same dozen objects and the same gate-wrapped routes. Building
 * them per-file would mean the tests could drift from each other AND from
 * public/index.php — and a permission test wired differently from the real
 * site proves nothing about the real site. One assembly, used by every admin
 * test, matching the front controller route for route.
 */
final class AdminWorld
{
    public Router $router;
    public Session $session;
    public Csrf $csrf;
    public UserRepository $users;
    public RoleRepository $roles;
    public AuditLogRepository $auditLog;
    public SecondFactorRepository $secondFactors;
    public Totp $totp;
    public AdminGate $gate;
    public PasswordHasher $hasher;
    public RoleAssignmentService $assignments;

    // The one password every test account uses. A constant, not a secret.
    public const PASSWORD = 'a-good-password';

    public static function build(PDO $connection): self
    {
        $world = new self();
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $security = $config['security'];

        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $world->session = new Session(cookieSecure: false);
        $world->csrf = new Csrf($world->session);
        $templates->registerFunction('csrf_field', function () use ($world): string {
            return '<input type="hidden" name="_csrf_token" value="'
                . htmlspecialchars($world->csrf->token(), ENT_QUOTES, 'UTF-8') . '">';
        });

        $world->users = new UserRepository($connection);
        $world->roles = new RoleRepository($connection);
        $world->auditLog = new AuditLogRepository($connection);
        $world->hasher = new PasswordHasher();
        $world->secondFactors = new SecondFactorRepository($connection, $world->hasher);
        $world->totp = new Totp();
        $rateLimiter = new RateLimiter(new RateLimitRepository($connection));

        $world->gate = new AdminGate(
            $world->session, $world->users, $world->roles, $templates,
            $security['admin']
        );
        $world->assignments = new RoleAssignmentService(
            $connection, $world->roles, $world->users, $world->auditLog
        );

        $doorController = new AdminDoorController(
            $templates, $world->session, $world->csrf, $world->users, $world->hasher,
            $world->totp, $world->secondFactors, $world->auditLog, $world->gate,
            $rateLimiter, $security['rate_limit_admin_door']
        );
        $enrolController = new AdminEnrolController(
            $templates, $world->session, $world->csrf, $world->users, $world->totp,
            $world->secondFactors, $world->auditLog, $world->gate, $connection
        );
        $homeController = new AdminHomeController($templates, $world->session, $world->roles);
        $rolesController = new AdminRolesController(
            $templates, $world->session, $world->csrf, $world->users, $world->roles,
            $world->assignments, $rateLimiter, $security['rate_limit_admin_role_change']
        );
        $auditController = new AdminAuditController($templates, $world->auditLog);

        // The routes, EXACTLY as public/index.php registers them — every one
        // through the gate. A route added there must be added here (and to
        // the AdminGateTest matrix), or the matrix stops proving coverage.
        $gate = $world->gate;
        $world->router = new Router();
        $world->router->get('/admin', $gate->protect(null, [$homeController, 'show']));
        $world->router->get('/admin/door', $gate->protectDoorway([$doorController, 'show']));
        $world->router->post('/admin/door', $gate->protectDoorway([$doorController, 'submit']));
        $world->router->get('/admin/enrol', $gate->protectDoorway([$enrolController, 'show']));
        $world->router->post('/admin/enrol', $gate->protectDoorway([$enrolController, 'submit']));
        $world->router->get('/admin/roles', $gate->protect(Role::Owner, [$rolesController, 'show']));
        $world->router->post('/admin/roles/grant', $gate->protect(Role::Owner, [$rolesController, 'grant']));
        $world->router->post('/admin/roles/revoke', $gate->protect(Role::Owner, [$rolesController, 'revoke']));
        $world->router->get('/admin/audit', $gate->protect(Role::Owner, [$auditController, 'show']));

        return $world;
    }

    /**
     * Create a plain player account with the standard test password.
     */
    public function makeUser(string $username): User
    {
        return $this->users->create(
            $username,
            $username . '@example.com',
            $this->hasher->hash(self::PASSWORD)
        );
    }

    /**
     * Create an account already holding the given roles (granted directly,
     * as bin/grant-role.php would).
     */
    public function makeStaff(string $username, Role ...$heldRoles): User
    {
        $user = $this->makeUser($username);
        foreach ($heldRoles as $role) {
            $this->roles->grant($user->id, $role, null);
        }

        return $user;
    }

    /**
     * Log this user in, in the fake test session.
     */
    public function logIn(User $user): void
    {
        $_SESSION['user_id'] = $user->id;
    }

    /**
     * Stamp the panel door as freshly passed for this user — for tests about
     * what lies BEYOND the door. The door's own checks have their own tests
     * (AdminDoorTest), which never use this.
     */
    public function passDoorFor(User $user): void
    {
        $_SESSION[AdminGate::SESSION_CONFIRMED] = ['user_id' => $user->id, 'at' => time()];
    }
}
