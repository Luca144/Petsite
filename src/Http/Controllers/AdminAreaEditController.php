<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentVersionRepository;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Auth\Session;
use Felkyo\Exploration\AreaEditorService;
use Felkyo\Exploration\AreaRepository;
use Felkyo\Exploration\LootLineParser;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * One area, everything about it, edited in place — words, scene, spots,
 * and the loot table.
 *
 * @package Felkyo\Http\Controllers
 *
 * The same handle() shape as every editor. Two things are special here:
 *
 *  - THE LOOT FIELD NEVER LOSES TYPING. Post/Redirect/Get would normally
 *    drop a refused form body, so a refused loot save stashes the typed
 *    text in the session; show() prefers the stash over the formatted
 *    current table and clears it. The all-or-nothing promise stays whole:
 *    either the table saved, or every character typed is still there
 *    beside the line-numbered reasons.
 *  - THE SPOTS come in as paired lists (spot_x[]/spot_y[]) — the number
 *    fields are the real form; the click-on-the-scene layer (area-spots.js)
 *    only fills them in.
 */
final class AdminAreaEditController
{
    // Where a refused loot save keeps the typing (per area).
    private const LOOT_STASH_PREFIX = 'area_loot_draft_';

    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private AreaRepository $areas,
        private ContentVersionRepository $versions,
        private AreaEditorService $editor,
        private ArtRepository $art,
        private LootLineParser $parser,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    /** @param array{id: string} $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $area = $this->areas->findById((int) ($parameters['id'] ?? 0));
        if ($area === null) {
            $this->session->flash('That area is gone.');

            return Response::redirect('/admin/areas');
        }

        // The loot field: the stashed typing of a refused save wins over the
        // formatted current table (class note), and is spent by this read.
        $stashKey = self::LOOT_STASH_PREFIX . $area->id;
        $stashedText = $this->session->get($stashKey);
        $this->session->remove($stashKey);
        $lootRows = $this->areas->lootFor($area->id);

        return Response::html($this->templates->render('pages/admin-area-edit', [
            'area' => $area,
            'lootRows' => $lootRows,
            'lootText' => is_string($stashedText) ? $stashedText : $this->parser->format($lootRows),
            'lootWasRefused' => is_string($stashedText),
            'missing' => $area->isPublished() ? [] : $this->editor->missingForPublish($area),
            'versions' => $this->versions->listFor('area', $area->id),
        ]));
    }

    /** @param array{id: string} $parameters */
    public function saveWords(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->saveWords($actor, $id, [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ], $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function saveSpots(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->saveSpots(
                $actor, $id,
                $request->inputList('spot_x'), $request->inputList('spot_y'),
                $request->clientIp()
            );
        });
    }

    /** @param array{id: string} $parameters */
    public function saveLoot(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            $text = $request->input('loot');
            $result = $this->editor->saveLoot($actor, $id, $text, $request->clientIp());

            // Keep every typed character across the redirect when refused;
            // clear any old stash on success (class note).
            $stashKey = self::LOOT_STASH_PREFIX . $id;
            if ($result->success) {
                $this->session->remove($stashKey);
            } else {
                $this->session->set($stashKey, $text);
            }

            return $result;
        });
    }

    /** @param array{id: string} $parameters */
    public function publish(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->publish($actor, $id, $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function unpublish(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->unpublish($actor, $id, $request->clientIp());
        });
    }

    /** @param array{id: string} $parameters */
    public function revert(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->revert($actor, $id, (int) $request->input('version_id', '0'), $request->clientIp());
        });
    }

    /**
     * The scene picker — the shared art-picker page, Backgrounds only.
     *
     * @param array{id: string} $parameters
     */
    public function pickPicture(Request $request, array $parameters): Response
    {
        $area = $this->areas->findById((int) ($parameters['id'] ?? 0));
        if ($area === null) {
            $this->session->flash('That area is gone.');

            return Response::redirect('/admin/areas');
        }

        $choices = array_values(array_filter(
            $this->art->all(),
            static fn ($picture): bool => $picture->kind === ArtKind::Background
        ));

        return Response::html($this->templates->render('pages/admin-art-picker', [
            'heading' => sprintf('A scene for %s', $area->name),
            'wantedKind' => ArtKind::Background,
            'choices' => $choices,
            'currentArtId' => $area->backgroundArtId,
            'action' => '/admin/areas/' . $area->id . '/picture',
            'backTo' => '/admin/areas/' . $area->id,
        ]));
    }

    /** @param array{id: string} $parameters */
    public function choosePicture(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->choosePicture($actor, $id, (int) $request->input('art_id', '0'), $request->clientIp());
        });
    }

    /**
     * User, CSRF, rate limit, the action, flash, back to the area's page.
     */
    private function handle(Request $request, array $parameters, callable $action): Response
    {
        $actor = $this->currentUser();
        $areaId = (int) ($parameters['id'] ?? 0);
        $backTo = '/admin/areas/' . $areaId;

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }

        if (!$this->rateLimiter->isAllowed(
            'content_save',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('content_save', 'user:' . $actor->id);

        $result = $action($actor, $areaId);
        $this->session->flash($result->message);

        // Back to the area's own page either way. A REFUSAL especially must
        // land here, not on the list: the reasons in the flash belong next
        // to the form they refuse — and a refused loot save has stashed the
        // typing for this page to show. (Refused results carry no contentId,
        // so keying the redirect on it sent refusals to the list. The smoke
        // test caught the typing seemingly vanishing that way.)
        return Response::redirect($backTo);
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The area editor was reached without a logged-in user.');
        }

        return $user;
    }
}
