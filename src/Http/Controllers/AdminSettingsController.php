<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\SettingsCatalog;
use Felkyo\Admin\SiteSettingsRepository;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Settings & maintenance — the Coder's one screen (M2.8).
 *
 * @package Felkyo\Http\Controllers
 *
 * The tunable numbers (through SettingsCatalog's allow-list, and ONLY
 * through it), and the small fixed set of named maintenance buttons.
 * There is no command runner and no query console here, and there never
 * will be — the build plan's absolute rule. A new maintenance need means
 * a new NAMED button in this file, with its own audit action.
 *
 * The controller holds the whole live (already-overridden) config so
 * the screen can show each setting's CURRENT value — the honest one,
 * after any panel change.
 */
final class AdminSettingsController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private SettingsCatalog $catalog,
        private SiteSettingsRepository $settings,
        private RateLimitRepository $rateLimitStore,
        private AuditLogRepository $auditLog,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
        private array $liveConfig,
    ) {
    }

    /** The screen: every setting with its explanation, then the buttons. */
    public function show(Request $request): Response
    {
        $overridden = array_keys($this->settings->overrides());

        $rows = [];
        foreach ($this->catalog->all() as $key => $entry) {
            $rows[] = [
                'key' => $key,
                'entry' => $entry,
                'current' => $this->catalog->currentFrom($key, $this->liveConfig),
                'isOverridden' => in_array($key, $overridden, true),
            ];
        }

        return Response::html($this->templates->render('pages/admin-settings', [
            'rows' => $rows,
            'artUsedBytes' => $this->artFolderBytes(),
            'artWarningBytes' => (int) $this->liveConfig['art']['volume_warning_bytes'],
        ]));
    }

    /** @param array{key: string} $parameters */
    public function save(Request $request, array $parameters): Response
    {
        return $this->handle($request, function (User $actor) use ($request, $parameters) {
            $key = (string) ($parameters['key'] ?? '');
            if (!$this->catalog->has($key)) {
                return 'That is not one of the editable settings.';
            }

            $checked = $this->catalog->validate($key, (string) $request->input('value'));
            if (isset($checked['error'])) {
                return $checked['error'];
            }

            $before = $this->catalog->currentFrom($key, $this->liveConfig);
            $this->settings->set($key, $checked['value']);
            $this->audit($actor, AuditAction::SettingChanged, $key,
                ['value' => $before], ['value' => $checked['value']], $request->clientIp());

            return 'Saved. It takes effect with the next page anyone loads.';
        });
    }

    /** @param array{key: string} $parameters */
    public function reset(Request $request, array $parameters): Response
    {
        return $this->handle($request, function (User $actor) use ($request, $parameters) {
            $key = (string) ($parameters['key'] ?? '');
            if (!$this->catalog->has($key)) {
                return 'That is not one of the editable settings.';
            }

            $before = $this->catalog->currentFrom($key, $this->liveConfig);
            $this->settings->reset($key);
            $this->audit($actor, AuditAction::SettingReset, $key,
                ['value' => $before], null, $request->clientIp());

            return 'Back to the built-in value, from the next page on.';
        });
    }

    /**
     * Named maintenance button: clear every rate-limit counter — for the
     * day somebody locks themselves out testing. Deletes only throttle
     * bookkeeping, never game data; still confirmed, because rule 9 says
     * nothing is lost to a slipped tap.
     */
    public function clearRateLimits(Request $request): Response
    {
        return $this->handle($request, function (User $actor) use ($request) {
            if ($request->input('confirm') !== 'yes') {
                return 'Nothing cleared — tick the little box to say you mean it.';
            }

            $cleared = $this->rateLimitStore->clearAll();
            $this->audit($actor, AuditAction::RateLimitsCleared, 'rate_limits',
                null, ['rows_cleared' => $cleared], $request->clientIp());

            return sprintf('The slate is clean — %d counter%s cleared.', $cleared, $cleared === 1 ? '' : 's');
        });
    }

    /**
     * Named maintenance button: every uploaded picture as one ZIP. The
     * art volume has NO platform backups (see the M2.2 notes), so THIS
     * BUTTON IS THE BACKUP STORY — press it after big art days and keep
     * the file somewhere safe.
     */
    public function artBackup(Request $request): Response
    {
        $actor = $this->currentUser();

        $files = glob(rtrim((string) $this->liveConfig['art']['storage_path'], '/\\') . '/*');
        $files = array_values(array_filter($files ?: [], 'is_file'));
        if ($files === []) {
            $this->session->flash('The art store is empty — nothing to back up yet.');

            return Response::redirect('/admin/settings');
        }

        // Built to a temp file and streamed from disk (Response::fileDownload)
        // — never through a PHP string; the store can be hundreds of MB.
        $zipPath = tempnam(sys_get_temp_dir(), 'felkyo-art') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        $this->audit($actor, AuditAction::ArtBackupDownloaded, 'art_store',
            null, ['files' => count($files)], $request->clientIp());

        return Response::fileDownload($zipPath, 'felkyo-art-backup-' . date('Y-m-d') . '.zip');
    }

    /** How full the art folder is, for the honest line on the screen. */
    private function artFolderBytes(): int
    {
        $bytes = 0;
        foreach (glob(rtrim((string) $this->liveConfig['art']['storage_path'], '/\\') . '/*') ?: [] as $file) {
            if (is_file($file)) {
                $bytes += (int) filesize($file);
            }
        }

        return $bytes;
    }

    /** User, CSRF, rate limit, the action, flash, back to the screen. */
    private function handle(Request $request, callable $action): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/settings');
        }

        if (!$this->rateLimiter->isAllowed(
            'settings',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That is a lot of changes for one hour — take a breather.');

            return Response::redirect('/admin/settings');
        }
        $this->rateLimiter->record('settings', 'user:' . $actor->id);

        $this->session->flash((string) $action($actor));

        return Response::redirect('/admin/settings');
    }

    private function audit(User $actor, AuditAction $action, string $subject, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: $action,
            subjectType: 'setting',
            subjectId: null,
            before: $before === null ? ['setting' => $subject] : ['setting' => $subject] + $before,
            after: $after,
            ip: $ip,
        ));
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The settings screen was reached without a logged-in user.');
        }

        return $user;
    }
}
