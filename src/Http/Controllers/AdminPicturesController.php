<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Art\ArtService;
use Felkyo\Art\ImageRefused;
use Felkyo\Art\ImageValidator;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The Pictures screen: upload a picture, see what is in the store, delete.
 *
 * @package Felkyo\Http\Controllers
 *
 * Web glue only. WHAT a picture may be is ImageValidator's business; WHO may
 * delete is ArtService's; this class carries the request in, flashes the
 * plain-words answer, and redirects (Post/Redirect/Get, like every form on
 * the site — a refreshed page must never upload twice).
 *
 * Reached only through AdminGate::protect(Role::Artist, …): artist or owner,
 * door passed, second factor enrolled.
 */
final class AdminPicturesController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private ArtRepository $pictures,
        private ArtService $artService,
        private ImageValidator $validator,
        private RateLimiter $rateLimiter,
        private array $uploadRateLimit,
        private array $artConfig,
    ) {
    }

    /**
     * The screen: the upload form, the grid, and the "disk getting full"
     * warning when it applies.
     */
    public function show(Request $request): Response
    {
        $actor = $this->currentUser();
        $totalBytes = $this->pictures->totalBytes();

        // Which pictures THIS person may delete decides which grid cards show
        // a delete button. Presentation only — the service re-checks.
        $pictures = [];
        foreach ($this->pictures->all() as $picture) {
            $pictures[] = [
                'file' => $picture,
                'canDelete' => $this->artService->mayDelete($actor, $picture),
            ];
        }

        return Response::html($this->templates->render('pages/admin-pictures', [
            'pictures' => $pictures,
            'kinds' => ArtKind::cases(),
            'totalBytes' => $totalBytes,
            'diskWarning' => $totalBytes >= (int) $this->artConfig['volume_warning_bytes'],
            'maxMegabytes' => intdiv((int) $this->artConfig['max_bytes'], 1024 * 1024),
            'maxDimension' => (int) $this->artConfig['max_dimension'],
            // The server's own cap on an upload, if it is LOWER than the
            // site's. PHP refuses such a file before the site sees it, so
            // the screen says so up front rather than blaming the file.
            'serverLimitMegabytes' => $this->serverLimitBelowSiteCap(),
        ]));
    }

    /**
     * PHP's own upload cap in MB if it is smaller than the site's — or null
     * when the server allows at least what the site does. The live server
     * sets its cap in the project's php.ini; a mismatch means that file was
     * not read, and this is the one place that would notice.
     */
    private function serverLimitBelowSiteCap(): ?int
    {
        $serverLimit = min(
            $this->iniBytes((string) ini_get('upload_max_filesize')),
            $this->iniBytes((string) ini_get('post_max_size'))
        );

        if ($serverLimit <= 0 || $serverLimit >= (int) $this->artConfig['max_bytes']) {
            return null;
        }

        return max(1, intdiv($serverLimit, 1024 * 1024));
    }

    /**
     * "8M" / "512K" / "1G" / "1048576" → bytes. PHP's shorthand for sizes.
     */
    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtoupper(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Upload one picture.
     */
    public function upload(Request $request): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return $this->back('That took too long — please try again.');
        }

        // The brake on filling the disk: per uploader, generous for a person.
        if (!$this->rateLimiter->isAllowed(
            'art_upload',
            'user:' . $actor->id,
            $this->uploadRateLimit['max_attempts'],
            $this->uploadRateLimit['window_seconds']
        )) {
            return $this->back('That\'s a lot of uploads for one hour. Take a breather, then carry on.');
        }

        $kind = ArtKind::fromFormValue($request->input('kind'));
        if ($kind === null) {
            return $this->back('Pick what kind of picture this is.');
        }

        // Several files at once (Skerro's species are 3+ sprites each). The
        // field is "pictures[]"; an old single "picture" still works too.
        $uploads = $request->fileList('pictures');
        if ($uploads === []) {
            $uploads = $request->fileList('picture');
        }
        $uploads = array_values(array_filter($uploads, static fn (array $file): bool => $file['error'] !== UPLOAD_ERR_NO_FILE));
        if ($uploads === []) {
            return $this->back('Choose a picture first.');
        }

        // One sentence per file, so a refused file says which and why.
        $lines = [];
        foreach ($uploads as $upload) {
            $lines[] = $this->uploadOne($request, $actor, $upload, $kind);
        }

        return $this->back(implode(' ', $lines));
    }

    /**
     * One file: rate limit, every safety check, store. Returns one sentence
     * naming the file. The rate limit counts FILES, not form submissions, so
     * uploading many at once is no way round it.
     *
     * @param array{name: string, tmp_name: string, size: int, error: int} $upload
     */
    private function uploadOne(Request $request, User $actor, array $upload, ArtKind $kind): string
    {
        $label = '"' . mb_substr(basename($upload['name']), 0, 60) . '":';

        if (!$this->rateLimiter->isAllowed(
            'art_upload',
            'user:' . $actor->id,
            $this->uploadRateLimit['max_attempts'],
            $this->uploadRateLimit['window_seconds']
        )) {
            return $label . ' skipped — that\'s a lot of uploads for one hour.';
        }

        // Every check that makes an upload safe lives in the validator; a
        // refusal arrives as one sentence for the person.
        try {
            $image = $this->validator->validate($upload);
        } catch (ImageRefused $refusal) {
            return $label . ' ' . $refusal->getMessage();
        }

        $this->rateLimiter->record('art_upload', 'user:' . $actor->id);

        return $label . ' ' . $this->artService->upload($actor, $image, $kind, $request->clientIp())->message;
    }

    /**
     * Delete one picture (owner: any; artist: their own — the service decides).
     *
     * @param array{id: string} $parameters
     */
    public function delete(Request $request, array $parameters): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return $this->back('That took too long — please try again.');
        }

        $pictureId = (int) ($parameters['id'] ?? 0);
        $result = $this->artService->delete($actor, $pictureId, $request->clientIp());

        return $this->back($result->message);
    }

    /**
     * Say what happened, then show the screen again.
     */
    private function back(string $message): Response
    {
        $this->session->flash($message);

        return Response::redirect('/admin/pictures');
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The pictures screen was reached without a logged-in user.');
        }

        return $user;
    }
}
