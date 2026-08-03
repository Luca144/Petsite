<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * Shows a single creature's page.
 *
 * @package Felkyo\Http\Controllers
 *
 * This gathers everything the creature page needs — the creature, its species,
 * its owner, and its calculated life stage — and renders it. It also enforces who
 * may see a creature: public creatures are visible to anyone (even logged-out
 * visitors), while a private creature is visible only to its owner.
 */
final class CreatureController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private CreatureRepository $creatures,
        private SpeciesRepository $species,
        private UserRepository $users,
        private GrowthCalculator $growth,
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

        $species = $this->species->findById($creature->speciesId);
        $owner = $this->users->findById($creature->ownerId);
        $stage = $this->growth->stageFor($creature->xp);

        $html = $this->templates->render('pages/creature', [
            'creature' => $creature,
            'species' => $species,
            'owner' => $owner,
            'stage' => $stage,
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
