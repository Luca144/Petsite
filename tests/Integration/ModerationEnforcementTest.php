<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Auth\Authenticator;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\ContentFilter;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Guestbook\GuestbookMessages;
use Felkyo\Guestbook\GuestbookRepository;
use Felkyo\Guestbook\GuestbookService;
use Felkyo\Http\Controllers\BioController;
use Felkyo\Http\Controllers\CreatureRenameController;
use Felkyo\Http\Controllers\GuestbookController;
use Felkyo\Http\Controllers\LoginController;
use Felkyo\Http\Controllers\NoticeController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use Felkyo\Moderation\ModerationRepository;
use Felkyo\Moderation\MutedVoice;
use Felkyo\Moderation\NoticeRepository;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Muting and suspension, enforced at the player-facing doors (M2.7).
 *
 * @package Felkyo\Tests\Integration
 *
 * The plan's promise: each free-text door REFUSES a muted player (bio,
 * guestbook signing, renaming here; the about text's refusing test lives
 * in ProfileControllerTest beside that door's other tests), an expired
 * mute needs nobody to lift it, a suspended account cannot log in, and a
 * notice can be acknowledged only by its own reader.
 */
final class ModerationEnforcementTest extends DatabaseTestCase
{
    private Router $router;
    private Csrf $csrf;
    private Session $session;
    private ModerationRepository $moderation;
    private NoticeRepository $notices;
    private CreatureRepository $creatures;
    private GuestbookRepository $guestbook;
    private UserRepository $users;
    private int $mutedId;
    private int $ownCreatureId;
    private int $neighbourCreatureId;

    private const PASSWORD = 'a-good-password';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('rate_limit_hits', 'moderation_notices', 'pettings', 'creatures', 'users');
        $_SESSION = [];

        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        \Felkyo\Texts\TextTemplateFunctions::register($templates, new \Felkyo\Texts\TextCatalogue());
        $this->session = new Session(cookieSecure: false);
        $this->csrf = new Csrf($this->session);
        $templates->registerFunction('csrf_field', function (): string {
            return '<input type="hidden" name="_csrf_token" value="'
                . htmlspecialchars($this->csrf->token(), ENT_QUOTES, 'UTF-8') . '">';
        });

        $this->users = new UserRepository($this->connection);
        $this->creatures = new CreatureRepository($this->connection);
        $this->guestbook = new GuestbookRepository($this->connection);
        $this->moderation = new ModerationRepository($this->connection);
        $this->notices = new NoticeRepository($this->connection);
        $rateLimiter = new RateLimiter(new RateLimitRepository($this->connection));
        $mutedVoice = new MutedVoice($this->moderation);
        $hasher = new PasswordHasher();

        // The player under moderation, with one creature of their own and a
        // neighbour's creature whose guestbook they could sign.
        $this->mutedId = $this->users->create('quiet', 'quiet@example.com', $hasher->hash(self::PASSWORD))->id;
        $neighbourId = $this->users->create('neighbour', 'neighbour@example.com', 'hash')->id;
        $speciesId = (new SpeciesRepository($this->connection))->findStarters()[0]->id;
        $this->ownCreatureId = $this->creatures->create($this->mutedId, $speciesId, 'Biscuit')->id;
        $this->neighbourCreatureId = $this->creatures->create($neighbourId, $speciesId, 'Clover')->id;

        // Every door, wired as the front controller wires it.
        $bioController = new BioController(
            $this->session, $this->csrf, $this->creatures,
            new CreatureBioService($this->creatures, Guards::textGuard(), 500),
            $mutedVoice, $rateLimiter, ['max_attempts' => 50, 'window_seconds' => 3600]
        );
        $guestbookController = new GuestbookController(
            $this->session, $this->csrf, $this->creatures,
            new GuestbookService($this->guestbook, new GuestbookMessages(['lovely-creature' => 'What a lovely creature.']), 86400),
            $this->guestbook, $mutedVoice, $rateLimiter,
            ['max_attempts' => 50, 'window_seconds' => 3600]
        );
        $renameController = new CreatureRenameController(
            $this->session, $this->csrf, $this->creatures, new ContentFilter(['spam']),
            $mutedVoice, $rateLimiter,
            ['max_attempts' => 50, 'window_seconds' => 3600], 40
        );
        $loginController = new LoginController(
            $templates, $this->csrf, $this->session,
            new Authenticator($this->users, $hasher), $this->users,
            $this->moderation, $rateLimiter, $config['security']
        );
        $noticeController = new NoticeController(
            $this->session, $this->csrf, $this->notices, $rateLimiter,
            ['max_attempts' => 30, 'window_seconds' => 3600]
        );

        $this->router = new Router();
        $this->router->post('/creature/{id}/bio', [$bioController, 'update']);
        $this->router->post('/creature/{id}/guestbook', [$guestbookController, 'sign']);
        $this->router->post('/creature/{id}/rename', [$renameController, 'update']);
        $this->router->post('/login', [$loginController, 'submit']);
        $this->router->post('/notices/{id}/read', [$noticeController, 'acknowledge']);
    }

    private function post(string $path, array $fields = []): Response
    {
        $fields['_csrf_token'] = $this->csrf->token();

        return $this->router->dispatch(new Request('POST', $path, $fields, '127.0.0.1'));
    }

    private function muteUntil(int $secondsFromNow): void
    {
        $this->moderation->setMutedUntil($this->mutedId, date('Y-m-d H:i:s', time() + $secondsFromNow));
    }

    public function testAMutedPlayerCannotWriteABio(): void
    {
        $_SESSION['user_id'] = $this->mutedId;
        $this->muteUntil(3600);

        $this->post('/creature/' . $this->ownCreatureId . '/bio', ['bio' => 'new words']);

        $this->assertStringContainsString('paused', $_SESSION['flash'] ?? '');
        $this->assertNull($this->creatures->findById($this->ownCreatureId)->bio, 'the bio must not change');
    }

    public function testAnExpiredMuteLiftsItselfWithNobodysHelp(): void
    {
        $_SESSION['user_id'] = $this->mutedId;
        $this->muteUntil(-60); // over, an hour ago-ish

        $this->post('/creature/' . $this->ownCreatureId . '/bio', ['bio' => 'kind words again']);

        $this->assertSame('kind words again', $this->creatures->findById($this->ownCreatureId)->bio);
    }

    public function testAMutedPlayerCannotSignAGuestbook(): void
    {
        $_SESSION['user_id'] = $this->mutedId;
        $this->muteUntil(3600);

        $this->post('/creature/' . $this->neighbourCreatureId . '/guestbook', ['message_key' => 'lovely-creature']);

        $this->assertStringContainsString('paused', $_SESSION['flash'] ?? '');
        $this->assertNull(
            $this->guestbook->findByCreatureAndAuthor($this->neighbourCreatureId, $this->mutedId),
            'no entry may land while muted'
        );
    }

    public function testAMutedPlayerCannotRenameACreature(): void
    {
        $_SESSION['user_id'] = $this->mutedId;
        $this->muteUntil(3600);

        $this->post('/creature/' . $this->ownCreatureId . '/rename', ['name' => 'Chaos']);

        $this->assertStringContainsString('paused', $_SESSION['flash'] ?? '');
        $this->assertSame('Biscuit', $this->creatures->findById($this->ownCreatureId)->name);
    }

    public function testASuspendedAccountCannotLogInAndIsToldUntilWhen(): void
    {
        $this->moderation->setSuspendedUntil($this->mutedId, date('Y-m-d H:i:s', time() + 86400));

        $response = $this->post('/login', ['username' => 'quiet', 'password' => self::PASSWORD]);

        $this->assertSame(403, $response->statusCode());
        $this->assertStringContainsString('paused until', $response->body());
        $this->assertStringContainsString('write to', $response->body(), 'the appeal address must be named — never a dead end');
    }

    public function testWrongPasswordAndSuspensionLookIdenticalToAStranger(): void
    {
        // The suspension answer must not become a "this account exists and is
        // in trouble" oracle: without the right password, it never appears.
        $this->moderation->setSuspendedUntil($this->mutedId, date('Y-m-d H:i:s', time() + 86400));

        $response = $this->post('/login', ['username' => 'quiet', 'password' => 'wrong-guess']);

        $this->assertSame(401, $response->statusCode());
        $this->assertStringNotContainsString('paused', $response->body());
    }

    public function testANoticeCanOnlyBeAcknowledgedByItsOwnReader(): void
    {
        $this->notices->send($this->mutedId, 'warned');
        $noticeId = $this->notices->allFor($this->mutedId)[0]->id;

        // A stranger acknowledging someone else's notice: same redirect,
        // nothing marked.
        $strangerId = $this->users->create('nosy', 'nosy@example.com', 'hash')->id;
        $_SESSION['user_id'] = $strangerId;
        $this->post('/notices/' . $noticeId . '/read');
        $this->assertCount(1, $this->notices->unreadFor($this->mutedId), 'a stranger must not clear it');

        // The reader themselves: marked read, card gone.
        $_SESSION['user_id'] = $this->mutedId;
        $this->post('/notices/' . $noticeId . '/read');
        $this->assertCount(0, $this->notices->unreadFor($this->mutedId));
        $this->assertNotNull($this->notices->allFor($this->mutedId)[0]->readAt);
    }
}
