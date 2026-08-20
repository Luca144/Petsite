<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Admin\AuditLogRepository;
use Felkyo\Auth\Authenticator;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\RegistrationService;
use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Http\Controllers\RegisterController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Router;
use Felkyo\Safety\ImpersonationGuard;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\UserRepository;
use Felkyo\Users\UserValidator;
use League\Plates\Engine;

/**
 * Privilege cannot be gained through any normal endpoint, and the audit log
 * cannot be edited through any code path at all.
 *
 * @package Felkyo\Tests\Integration
 *
 * The M2.1 plan's worst-case walk says escalation via a normal endpoint is
 * "impossible by construction: nothing outside the owner-only service and the
 * CLI script writes user_roles". These tests are that claim's teeth: the
 * registration form is fed role-shaped fields and must ignore every one, and
 * the audit repository is inspected for write methods it must never grow.
 */
final class AdminEscalationTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'user_roles', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        // The registration endpoint, wired as the front controller wires it
        // (the same assembly AuthControllerTest uses).
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($session);
        $templates->registerFunction('csrf_field', function (): string {
            return '<input type="hidden" name="_csrf_token" value="'
                . htmlspecialchars($this->csrf->token(), ENT_QUOTES, 'UTF-8') . '">';
        });

        $this->users = new UserRepository($this->connection);
        $hasher = new PasswordHasher();
        $registration = new RegistrationService(
            $this->users, new UserValidator($config['security']), $hasher,
            Guards::textGuard(), new ImpersonationGuard(), 30
        );
        $starterCreatures = new StarterCreatureService(
            new SpeciesRepository($this->connection),
            new CreatureRepository($this->connection),
            $config['gameplay']['creature_names']
        );
        $registerController = new RegisterController(
            $templates, $this->csrf, $session, $registration, $starterCreatures,
            new RateLimiter(new RateLimitRepository($this->connection)),
            $config['security'], true
        );

        $this->router = new Router();
        $this->router->post('/register', [$registerController, 'submit']);
    }

    public function testRegisteringWithRoleShapedFieldsGrantsNoRole(): void
    {
        // A hostile sign-up posts every field name an escalation would need.
        // Registration must succeed as a PLAIN account and ignore them all.
        $response = $this->router->dispatch(new Request('POST', '/register', [
            '_csrf_token' => $this->csrf->token(),
            'username' => 'sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'a-good-password',
            'role' => 'owner',
            'roles' => 'owner',
            'is_admin' => '1',
            'user_roles' => 'owner',
        ], '127.0.0.1'));

        $this->assertSame(302, $response->statusCode(), 'the sign-up itself should work normally');

        $created = $this->users->findByUsername('sneaky');
        $this->assertNotNull($created);

        // The whole point: not one row in user_roles, for anyone.
        $roleRows = $this->connection->query('SELECT COUNT(*) FROM user_roles')->fetchColumn();
        $this->assertSame(0, (int) $roleRows, 'no normal endpoint may write user_roles');
    }

    public function testTheAuditRepositoryHasNoWayToEditOrDeleteEntries(): void
    {
        // Append-only is a promise about the CLASS, so the test reads the
        // class: every public method must be a write-one or a read, and none
        // may smell of update or delete. A future "cleanup" method lands here
        // first — and this failure message explains what it would break.
        $methods = get_class_methods(AuditLogRepository::class);

        foreach ($methods as $method) {
            foreach (['delete', 'remove', 'update', 'edit', 'purge', 'prune', 'clear', 'truncate'] as $forbidden) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $forbidden,
                    $method,
                    "AuditLogRepository::{$method}() looks like it edits the log. The audit log is append-only "
                    . 'on purpose — it is the witness that outlives whoever it describes (see the class docblock).'
                );
            }
        }

        // And the SQL the class contains must never say DELETE or UPDATE.
        // (Uppercase only: that is how SQL keywords are written throughout
        // this codebase, while the words in prose comments are lowercase.)
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/src/Admin/AuditLogRepository.php'
        );
        $this->assertDoesNotMatchRegularExpression('/\b(DELETE|UPDATE)\b/', $source,
            'AuditLogRepository must contain no destructive SQL');
    }
}
