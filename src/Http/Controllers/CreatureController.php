<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Guestbook\GuestbookPanel;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * Shows a single creature's page.
 *
 * @package Felkyo\Http\Controllers
 *
 * This loads the creature, asks CreatureProfileBuilder for everything needed to
 * display it, and renders the page. It also enforces who may see a creature:
 * public creatures are visible to anyone (even logged-out visitors), while a
 * private creature is visible only to its owner.
 */
final class CreatureController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private CreatureRepository $creatures,
        private CreatureProfileBuilder $profileBuilder,
        private GuestbookPanel $guestbookPanel,
        // The rename field needs the same limit the server enforces, so the box
        // stops you before a submission can fail. One number, from config.
        private int $nameMaxLength,
    ) {
    }

    /**
     * Show the page for the creature whose id is in the URL (e.g. /creature/42).
     *
     * @param array<string, string> $parameters Captured route parameters.
     */
    public function show(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $creature = $this->creatures->findById($creatureId);

        // "Does this creature exist?" — if not, a normal not-found page.
        if ($creature === null) {
            return $this->notFound();
        }

        // "Who can see it?" — a private creature is hidden from everyone but its
        // owner. We return the SAME not-found result as for a missing creature, so
        // a private creature's existence is not revealed to outsiders.
        if (!$creature->isPublic && !$this->viewerOwns($creature->ownerId)) {
            return $this->notFound();
        }

        $profile = $this->profileBuilder->buildFor($creature);

        // The guestbook needs to know who is looking, so it can show this visitor
        // their own entry as already selected. A guest passes null.
        $viewerId = $this->session->get('user_id');
        $guestbook = $this->guestbookPanel->forCreature(
            $creature,
            is_int($viewerId) ? $viewerId : null
        );

        $html = $this->templates->render('pages/creature', [
            'creature' => $creature,
            'species' => $profile['species'],
            'owner' => $profile['owner'],
            'level' => $profile['level'],
            'stage' => $profile['stage'],
            'timesPetted' => $profile['timesPetted'],
            // Any logged-in visitor may pet a creature they can see.
            'canPet' => $this->session->has('user_id'),
            // Only the owner sees the "edit bio" and "rename" forms.
            'isOwner' => $this->viewerOwns($creature->ownerId),
            'nameMaxLength' => $this->nameMaxLength,
            // Reporting needs somebody to report TO, so the control is offered
            // only to a logged-in visitor (the owner gets the edit form instead).
            'viewerIsLoggedIn' => is_int($this->session->get('user_id')),
            // The guestbook: its signatures, the choosable messages, and which one
            // this visitor already picked. Any logged-in visitor may sign.
            'guestbook' => $guestbook,
            'canSignGuestbook' => $this->session->has('user_id'),
            // A one-time message from a just-completed action (e.g. after petting).
            'flash' => $this->session->takeFlash(),
            // True just after a successful pet, so the page plays the heart once.
            'justPetted' => $this->session->takeCelebration() === 'pet',
        ]);

        return Response::html($html);
    }

    /**
     * Is the current visitor the owner of this creature?
     */
    private function viewerOwns(int $ownerId): bool
    {
        $viewerId = $this->session->get('user_id');

        return is_int($viewerId) && $viewerId === $ownerId;
    }

    /**
     * Render a simple "not found" page with a 404 status. The friendly, themed
     * 404 is polished in increment C.2; this reuses a small shared template.
     */
    private function notFound(): Response
    {
        $html = $this->templates->render('pages/not-found', [
            'message' => 'We couldn\'t find that creature.',
        ]);

        return Response::html($html, 404);
    }
}
