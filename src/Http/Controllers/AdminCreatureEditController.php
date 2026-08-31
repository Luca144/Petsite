<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\SpeciesEditorService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\SpeciesVersionRepository;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * THE page: everything about one species, edited in one place.
 *
 * @package Felkyo\Http\Controllers
 *
 * The screen M2 exists for. Each section is its own small form — words,
 * one card per stage, settings, publish — so "save as you go" is literal:
 * every submit is Post/Redirect/Get back to this page with one sentence
 * about what happened. All rules live in SpeciesEditorService; this class
 * carries requests in and flashes answers out, exactly like the other
 * panel controllers.
 *
 * SEVEN ACTIONS, ONE SHAPE: every POST runs the same three checks (user,
 * CSRF, rate limit) via handle(), then its one service call. The
 * repetition a reader sees is the point — no action can forget a check.
 */
final class AdminCreatureEditController
{
    /**
     * @param string[] $stageNames from config growth.stage_start_levels keys.
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private SpeciesRepository $species,
        private SpeciesVersionRepository $versions,
        private SpeciesEditorService $editor,
        private InventoryRepository $inventory,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
        private array $stageNames,
    ) {
    }

    /**
     * The editor page for one species.
     *
     * @param array{id: string} $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        $species = $this->species->findById((int) ($parameters['id'] ?? 0));
        if ($species === null) {
            $this->session->flash('That species is gone.');

            return Response::redirect('/admin/creatures');
        }

        return Response::html($this->templates->render('pages/admin-creature-edit', [
            'species' => $species,
            'stageNames' => $this->stageNames,
            'missing' => $species->isPublished() ? [] : $this->editor->missingForPublish($species),
            // The taste picker's choices: every feedable item, as tiles.
            'treats' => $this->inventory->findAllTreats(),
            'versions' => $this->versions->listFor($species->id),
        ]));
    }

    // ---- The seven actions, all one shape ----

    /** @param array{id: string} $parameters */
    public function saveWords(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->saveWords($actor, $id, [
                'name' => $request->input('name'),
                'flavour_text' => $request->input('flavour_text'),
                'lore' => $request->input('lore'),
            ], $request->clientIp());
        });
    }

    /** @param array{id: string, stage: string} $parameters */
    public function saveStage(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request, $parameters) {
            return $this->editor->saveStageBlurb(
                $actor, $id, (string) ($parameters['stage'] ?? ''), $request->input('blurb'), $request->clientIp()
            );
        });
    }

    /** @param array{id: string} $parameters */
    public function saveSettings(Request $request, array $parameters): Response
    {
        return $this->handle($request, $parameters, function (User $actor, int $id) use ($request) {
            return $this->editor->saveSettings($actor, $id, [
                'gem_price' => (int) $request->input('gem_price', '0'),
                // Checkboxes: present when ticked, absent when not.
                'is_adoptable' => $request->input('is_adoptable') !== '',
                'is_starter' => $request->input('is_starter') !== '',
                'favourite_item_id' => $request->input('favourite_item_id'),
                'disliked_item_id' => $request->input('disliked_item_id'),
            ], $request->clientIp());
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

    // ---- The one shape itself ----

    /**
     * User, CSRF, rate limit, then the action; flash the sentence; return
     * to the species page. Every POST above is this and nothing more.
     */
    private function handle(Request $request, array $parameters, callable $action): Response
    {
        $actor = $this->currentUser();
        $speciesId = (int) ($parameters['id'] ?? 0);
        $backTo = '/admin/creatures/' . $speciesId;

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }

        if (!$this->rateLimiter->isAllowed(
            'species_save',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('species_save', 'user:' . $actor->id);

        $result = $action($actor, $speciesId);
        $this->session->flash($result->message);

        return Response::redirect($result->speciesId !== null ? '/admin/creatures/' . $result->speciesId : '/admin/creatures');
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The species editor was reached without a logged-in user.');
        }

        return $user;
    }
}
