<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\ProfileRepository;
use League\Plates\Engine;

/**
 * The Community page — browse creatures and search for players from one place.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHAT THIS IS: combines "Browse Creatures" and "Find People" into a single page
 * with tabs. The content for each tab comes from existing repositories and builders
 * (CreatureRepository, ProfileRepository) — this controller just wires them together
 * with a tab interface.
 */
final class CommunityController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private CreatureRepository $creatures,
        private CreatureProfileBuilder $profileBuilder,
        private ProfileRepository $profiles,
        private AvatarSet $avatarSet,
        private int $browseLimit,
    ) {
    }

    /**
     * Show the community page. Query string "tab" determines which tab is active.
     * Default: "creatures" (browse creatures).
     */
    public function show(Request $request): Response
    {
        $tab = $request->query('tab') ?? 'creatures';
        $query = $request->query('q') ?? '';

        // Load recent public creatures (for the "browse creatures" tab)
        $recentCreatures = $this->creatures->findRecentPublic($this->browseLimit);
        $creatureSummaries = $this->profileBuilder->summariesFor($recentCreatures);

        // Load found players (for the "find people" tab) — only if a query exists
        $foundPlayers = [];
        if (!empty($query)) {
            $foundPlayers = $this->profiles->searchByUsername($query);
        }

        $currentUser = null;
        $userId = $this->session->get('user_id');
        if (is_int($userId)) {
            $currentUser = $this->profiles->findById($userId);
        }

        $html = $this->templates->render('pages/community', [
            'tab' => $tab,
            'query' => $query,
            'creatureSummaries' => $creatureSummaries,
            'foundPlayers' => $foundPlayers,
            'currentUser' => $currentUser,
            'avatarSet' => $this->avatarSet,
        ]);

        return Response::html($html);
    }
}
