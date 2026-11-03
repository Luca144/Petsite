<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Auth\Session;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Library\FormEditorService;
use Felkyo\Library\SpeciesFormRepository;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The panel's alternate-forms screen for one species (M5.3).
 *
 * @package Felkyo\Http\Controllers
 *
 *   GET  /admin/creatures/{id}/forms                 the forms of a species
 *   POST /admin/creatures/{id}/forms/create          start a new one
 *   POST /admin/forms/{formId}/words|publish|unpublish|delete
 *   GET  /admin/forms/{formId}/picture/{stage}       the sprite picker
 *   POST /admin/forms/{formId}/picture/{stage}
 *
 * Every route is wrapped by AdminGate (artist role) in index.php. Every POST
 * goes through handle(): logged-in user, CSRF, the shared content-save rate
 * limit, then exactly one service call — the same shape as every other
 * panel editor, so no action can skip a check.
 */
final class AdminFormsController
{
    /** @param string[] $stageNames */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private SpeciesRepository $species,
        private SpeciesFormRepository $forms,
        private ArtRepository $art,
        private FormEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
        private array $stageNames,
    ) {
    }

    /** @param array{id: string} $parameters */
    public function show(Request $request, array $parameters): Response
    {
        $species = $this->species->findById((int) ($parameters['id'] ?? 0));
        if ($species === null) {
            $this->session->flash('That species is gone.');

            return Response::redirect('/admin/creatures');
        }

        return Response::html($this->templates->render('pages/admin-forms', [
            'species' => $species,
            'forms' => $this->forms->forSpecies($species->id, false),
            'stageNames' => $this->stageNames,
        ]));
    }

    /** @param array{id: string} $parameters */
    public function create(Request $request, array $parameters): Response
    {
        $speciesId = (int) ($parameters['id'] ?? 0);

        return $this->handle($request, $speciesId, fn (User $actor) =>
            $this->editor->create($actor, $speciesId, $request->input('name'), $request->clientIp()));
    }

    /** @param array{formId: string} $parameters */
    public function saveWords(Request $request, array $parameters): Response
    {
        return $this->forForm($request, $parameters, fn (User $actor, int $formId) =>
            $this->editor->saveWords($actor, $formId, $request->input('name'), $request->input('blurb'), $request->clientIp()));
    }

    /** @param array{formId: string} $parameters */
    public function publish(Request $request, array $parameters): Response
    {
        return $this->forForm($request, $parameters, fn (User $actor, int $formId) =>
            $this->editor->setPublished($actor, $formId, true, $request->clientIp()));
    }

    /** @param array{formId: string} $parameters */
    public function unpublish(Request $request, array $parameters): Response
    {
        return $this->forForm($request, $parameters, fn (User $actor, int $formId) =>
            $this->editor->setPublished($actor, $formId, false, $request->clientIp()));
    }

    /** @param array{formId: string} $parameters */
    public function delete(Request $request, array $parameters): Response
    {
        return $this->forForm($request, $parameters, fn (User $actor, int $formId) =>
            $this->editor->delete($actor, $formId, $request->clientIp()));
    }

    /**
     * The sprite picker for one stage of a form — the shared picker page.
     *
     * @param array{formId: string, stage: string} $parameters
     */
    public function pickPicture(Request $request, array $parameters): Response
    {
        $form = $this->forms->findById((int) ($parameters['formId'] ?? 0));
        $stage = (string) ($parameters['stage'] ?? '');
        if ($form === null || !in_array($stage, $this->stageNames, true)) {
            $this->session->flash('That picture slot doesn\'t exist.');

            return Response::redirect('/admin/creatures');
        }

        return Response::html($this->templates->render('pages/admin-art-picker', [
            'heading' => sprintf('The %s sprite for %s', $stage, $form->name),
            'wantedKind' => ArtKind::Sprite,
            'choices' => array_values(array_filter(
                $this->art->all(),
                static fn ($picture): bool => $picture->kind === ArtKind::Sprite
            )),
            'currentArtId' => $form->spriteArtIdFor($stage),
            'action' => '/admin/forms/' . $form->id . '/picture/' . rawurlencode($stage),
            'backTo' => '/admin/creatures/' . $form->speciesId . '/forms',
        ]));
    }

    /** @param array{formId: string, stage: string} $parameters */
    public function choosePicture(Request $request, array $parameters): Response
    {
        return $this->forForm($request, $parameters, fn (User $actor, int $formId) =>
            $this->editor->choosePicture(
                $actor, $formId, (string) ($parameters['stage'] ?? ''),
                (int) $request->input('art_id', '0'), $request->clientIp()
            ));
    }

    /**
     * Find which species a form belongs to (for the way back), then handle().
     */
    private function forForm(Request $request, array $parameters, callable $action): Response
    {
        $formId = (int) ($parameters['formId'] ?? 0);
        $form = $this->forms->findById($formId);
        if ($form === null) {
            $this->session->flash('That form is gone.');

            return Response::redirect('/admin/creatures');
        }

        return $this->handle($request, $form->speciesId, fn (User $actor) => $action($actor, $formId));
    }

    /**
     * User, CSRF, rate limit, the one action, flash, back to the forms page.
     *
     * @param callable(User): ContentEditResult $action
     */
    private function handle(Request $request, int $speciesId, callable $action): Response
    {
        $backTo = '/admin/creatures/' . $speciesId . '/forms';
        $actor = $this->users->findById((int) $this->session->get('user_id'));
        if ($actor === null) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }

        $bucket = 'user:' . $actor->id;
        $limit = $this->saveRateLimit;
        if (!$this->rateLimiter->isAllowed('content_save', $bucket, $limit['max_attempts'], $limit['window_seconds'])) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('content_save', $bucket);

        $this->session->flash($action($actor)->message);

        return Response::redirect($backTo);
    }
}
