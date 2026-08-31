<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Auth\Session;
use Felkyo\Creatures\SpeciesEditorService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\User;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Choosing a picture for one slot of one species — a page, not a pop-up.
 *
 * @package Felkyo\Http\Controllers
 *
 * The editor's stage cards and library slot link here. The page shows the
 * art store's pictures OF THE RIGHT KIND (sprites for a stage, library art
 * for the library) as a tappable grid; choosing one links it and returns to
 * the editor. A whole page rather than a modal because that is how this
 * site works everywhere: no JS framework, keyboard-reachable, nothing lost
 * to a mis-tap (the choice is one POST, undone by choosing again).
 *
 * WHICH kinds fit WHICH slot is SpeciesEditorService's rule; this class
 * only pre-filters the grid so the artist is not offered pictures the
 * service would refuse.
 */
final class AdminCreaturePickerController
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
        private ArtRepository $art,
        private SpeciesEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
        private array $stageNames,
    ) {
    }

    /**
     * The grid for one slot.
     *
     * @param array{id: string, slot: string} $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        $species = $this->species->findById((int) ($parameters['id'] ?? 0));
        $slot = (string) ($parameters['slot'] ?? '');

        if ($species === null || !$this->isSlot($slot)) {
            $this->session->flash('That picture slot doesn\'t exist.');

            return Response::redirect('/admin/creatures');
        }

        // Only the kind this slot takes — the same rule the service enforces.
        $wantedKind = $slot === 'library' ? ArtKind::Library : ArtKind::Sprite;
        $choices = array_values(array_filter(
            $this->art->all(),
            static fn ($picture): bool => $picture->kind === $wantedKind
        ));

        return Response::html($this->templates->render('pages/admin-creature-picker', [
            'species' => $species,
            'slot' => $slot,
            'wantedKind' => $wantedKind,
            'choices' => $choices,
            'currentArtId' => $slot === 'library'
                ? $species->libraryArtId
                : $species->spriteArtIdFor($slot),
        ]));
    }

    /**
     * Link the chosen picture (art_id 0 = unlink) and go back to the editor.
     *
     * @param array{id: string, slot: string} $parameters
     */
    public function choose(Request $request, array $parameters): Response
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

        $result = $this->editor->choosePicture(
            $actor,
            $speciesId,
            (string) ($parameters['slot'] ?? ''),
            (int) $request->input('art_id', '0'),
            $request->clientIp()
        );
        $this->session->flash($result->message);

        return Response::redirect($backTo);
    }

    /** A slot is a stage name or the library. */
    private function isSlot(string $slot): bool
    {
        return $slot === 'library' || in_array($slot, $this->stageNames, true);
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The picture picker was reached without a logged-in user.');
        }

        return $user;
    }
}
