<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\AdminGate;
use Felkyo\Admin\Role;
use Felkyo\Http\Request;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\AdminWorld;

/**
 * The refusal matrix: every role against every admin route.
 *
 * @package Felkyo\Tests\Integration
 *
 * M2.1's security plan promises "each role refused on every route outside its
 * scope" — this file IS that promise, as a data-driven walk of the full route
 * list. IF YOU ADD AN ADMIN ROUTE: add it to AdminWorld (wired through the
 * gate, like the front controller) and to the matrix below, or the matrix
 * stops meaning "every route".
 */
final class AdminGateTest extends DatabaseTestCase
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

    /**
     * Every admin route that lies BEYOND the door, with the role it needs
     * (null = any staff role). The door/enrol routes are covered separately —
     * they answer to different rules by design.
     *
     * @return array<int, array{string, string, ?Role}>
     */
    private function protectedRoutes(): array
    {
        return [
            ['GET', '/admin', null],
            ['GET', '/admin/roles', Role::Owner],
            ['POST', '/admin/roles/grant', Role::Owner],
            ['POST', '/admin/roles/revoke', Role::Owner],
            ['GET', '/admin/audit', Role::Owner],
        ];
    }

    private function dispatch(string $method, string $path): \Felkyo\Http\Response
    {
        $post = $method === 'POST' ? ['_csrf_token' => $this->world->csrf->token()] : [];

        return $this->world->router->dispatch(new Request($method, $path, $post, '127.0.0.1'));
    }

    // --- Outsiders ---

    public function testAGuestIsSentToLogInOnEveryAdminRoute(): void
    {
        foreach ($this->protectedRoutes() as [$method, $path]) {
            $response = $this->dispatch($method, $path);

            $this->assertSame(302, $response->statusCode(), "{$method} {$path} should redirect a guest");
            $this->assertSame('/login', $response->header('Location'), "{$method} {$path} should send a guest to /login");
        }

        // The door and enrolment refuse guests the same way.
        foreach ([['GET', '/admin/door'], ['POST', '/admin/door'], ['GET', '/admin/enrol'], ['POST', '/admin/enrol']] as [$method, $path]) {
            $this->assertSame('/login', $this->dispatch($method, $path)->header('Location'));
        }
    }

    public function testAPlayerGetsTheSame404AsAnyUnknownPageOnEveryAdminRoute(): void
    {
        $player = $this->world->makeUser('biscuit');
        $this->world->logIn($player);
        $this->world->passDoorFor($player); // even a forged door stamp must not help

        foreach ($this->protectedRoutes() as [$method, $path]) {
            $this->assertSame(404, $this->dispatch($method, $path)->statusCode(),
                "{$method} {$path} should be a 404 for a player");
        }

        foreach ([['GET', '/admin/door'], ['POST', '/admin/door'], ['GET', '/admin/enrol'], ['POST', '/admin/enrol']] as [$method, $path]) {
            $this->assertSame(404, $this->dispatch($method, $path)->statusCode(),
                "{$method} {$path} should be a 404 for a player");
        }
    }

    // --- The matrix: each non-owner role, on every route ---

    public function testEachRoleIsRefusedOnEveryRouteOutsideItsScope(): void
    {
        foreach ([Role::Moderator, Role::Artist, Role::Coder] as $role) {
            $_SESSION = [];
            $staff = $this->world->makeStaff('staff-' . $role->value, $role);
            $this->world->logIn($staff);
            $this->world->passDoorFor($staff);

            foreach ($this->protectedRoutes() as [$method, $path, $required]) {
                $response = $this->dispatch($method, $path);

                if ($required === null) {
                    // The panel home: any staff role may see it.
                    $this->assertSame(200, $response->statusCode(),
                        "{$role->value} should see {$method} {$path}");
                } else {
                    // An owner-only screen: refused with the plain-words 403,
                    // which names the missing role.
                    $this->assertSame(403, $response->statusCode(),
                        "{$role->value} should be refused on {$method} {$path}");
                    $this->assertStringContainsString($required->label(), $response->body());
                }
            }
        }
    }

    public function testAnOwnerPassesEveryCheck(): void
    {
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);
        $this->world->passDoorFor($owner);

        foreach ($this->protectedRoutes() as [$method, $path]) {
            $status = $this->dispatch($method, $path)->statusCode();

            // GETs render (200); the POSTs flash-and-redirect (302). Neither
            // is ever a refusal status for an owner.
            $this->assertContains($status, [200, 302], "owner should pass {$method} {$path} (got {$status})");
        }
    }

    // --- The door's freshness, and revocation taking effect immediately ---

    public function testAStaleDoorConfirmationRedirectsBackToTheDoor(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $maxAge = $config['security']['admin']['confirm_max_age_seconds'];

        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $this->world->logIn($owner);
        // A confirmation from just past the allowed age.
        $_SESSION[AdminGate::SESSION_CONFIRMED] = ['user_id' => $owner->id, 'at' => time() - $maxAge - 5];

        $response = $this->dispatch('GET', '/admin');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/admin/door', $response->header('Location'));
    }

    public function testAnotherUsersDoorConfirmationDoesNotOpenTheDoorForYou(): void
    {
        // The session survives logout-then-login; a stamp naming a DIFFERENT
        // user must not carry over (see AdminGate's class docblock).
        $owner = $this->world->makeStaff('skerro', Role::Owner);
        $secondOwner = $this->world->makeStaff('oki', Role::Owner);
        $this->world->logIn($owner);
        $this->world->passDoorFor($secondOwner);

        $response = $this->dispatch('GET', '/admin');

        $this->assertSame(302, $response->statusCode());
        $this->assertSame('/admin/door', $response->header('Location'));
    }

    public function testARevokedRoleStopsWorkingOnTheVeryNextRequest(): void
    {
        $artist = $this->world->makeStaff('oki', Role::Artist);
        $this->world->logIn($artist);
        $this->world->passDoorFor($artist);

        $this->assertSame(200, $this->dispatch('GET', '/admin')->statusCode());

        // Revoke mid-session — no logout, nothing else changes.
        $this->world->roles->revoke($artist->id, Role::Artist);

        $this->assertSame(404, $this->dispatch('GET', '/admin')->statusCode(),
            'a revoked role must be gone on the next request, not the next login');
    }

    public function testTheReturnPathAfterTheDoorOnlyEverPointsIntoThePanel(): void
    {
        // The gate remembers where a GET was headed; anything not under
        // /admin is discarded in favour of the panel home.
        $_SESSION[AdminGate::SESSION_RETURN_TO] = '/player/somebody';
        $this->assertSame('/admin', $this->world->gate->takeReturnPath());

        $_SESSION[AdminGate::SESSION_RETURN_TO] = '/admin/roles';
        $this->assertSame('/admin/roles', $this->world->gate->takeReturnPath());
    }
}
