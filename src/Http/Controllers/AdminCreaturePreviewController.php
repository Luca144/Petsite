<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\Creature;
use Felkyo\Creatures\MoodCalculator;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * Preview: a species on the REAL creature layout, before anybody meets it.
 *
 * @package Felkyo\Http\Controllers
 *
 * The build plan asks for "preview at any stage, rendered on the real page
 * layout, phone and desktop, before publishing". So this page renders the
 * genuine creature sheet — the same partial the live creature page uses,
 * with the same sprite sizing, the same mood bars, the same type — around a
 * STAND-IN creature of this species at the chosen stage. Open it on a
 * phone and it IS the phone layout; nothing is imitated.
 *
 * The stand-in is never saved anywhere: it exists for one render, with the
 * XP its stage starts at, a sunny mood, and the species' own name.
 */
final class AdminCreaturePreviewController
{
    /**
     * @param array $growthConfig config gameplay.growth: xp_per_level and
     *                            stage_start_levels (the stages in order).
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private SpeciesRepository $species,
        private MoodCalculator $mood,
        private array $growthConfig,
    ) {
    }

    /**
     * @param array{id: string} $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        $species = $this->species->findById((int) ($parameters['id'] ?? 0));
        if ($species === null) {
            $this->session->flash('That species is gone.');

            return Response::redirect('/admin/creatures');
        }

        $stageNames = array_keys($this->growthConfig['stage_start_levels']);

        // Which stage to stand at: from the address, or the first one.
        $stage = $request->query('stage');
        if (!in_array($stage, $stageNames, true)) {
            $stage = $stageNames[0];
        }

        // The XP this stage begins at — so the sheet's level and stage read
        // exactly what a real creature entering the stage would show.
        $startLevel = (int) $this->growthConfig['stage_start_levels'][$stage];
        $xp = ($startLevel - 1) * (int) $this->growthConfig['xp_per_level'];

        // The stand-in (class note): one render's worth of creature.
        $standIn = new Creature(
            id: 0,
            ownerId: 0,
            speciesId: $species->id,
            name: $species->name,
            xp: $xp,
            happiness: 90,
            happinessAgeSeconds: 0,
            energy: 100,
            energyAgeSeconds: 0,
            bio: null,
            bioHiddenAt: null,
            isPublic: false,
            featuredOrder: null,
            lastInteractedAt: null,
            createdAt: null,
        );

        return Response::html($this->templates->render('pages/admin-creature-preview', [
            'species' => $species,
            'creature' => $standIn,
            'stage' => $stage,
            'stageNames' => $stageNames,
            'level' => $startLevel,
            'mood' => $this->mood->moodFor(90, 0, 100, 0),
        ]));
    }
}
