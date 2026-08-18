<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Users\PlayerFinder;
use League\Plates\Engine;

/**
 * The Community page — the creatures of Felkyo and the people who keep them,
 * behind two tabs on one page.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHY ONE PAGE: browsing creatures and looking for a player are the same errand
 * ("who else is here?"), and they were previously two separate pills in the world
 * bar. Folding them into one destination with two tabs shortens the bar to
 * something glanceable on a phone, which is what the mockup asks for.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6):
 *
 * 1. WHO IS ALLOWED? Anyone may browse creatures — only PUBLIC ones are ever
 *    fetched (the filter is in CreatureRepository::findRecentPublic, not here).
 *    Only a logged-in player may search for people, for the same reason as on
 *    /players: an open search box is the cheapest way to script through a
 *    playerbase, and an account puts a rate-limitable identity behind each query.
 *
 * 2. WHAT IS THE WORST A MALICIOUS USER COULD DO? Enumerate players. That threat
 *    is answered by PlayerFinder — minimum length, result cap, rate limit, prefix
 *    matching — which is shared with SearchController rather than reimplemented
 *    here. The first version of this page DID reimplement it, and shipped with
 *    none of the guards; that is why the policy now lives in one class.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? CommunityControllerTest proves a guest is
 *    turned away from the people tab, that a too-short query searches nothing,
 *    and that private creatures never appear on the creatures tab.
 */
final class CommunityController
{
    /** The tabs this page offers. Anything else falls back to the first. */
    private const TABS = ['creatures', 'users'];

    public function __construct(
        private Engine $templates,
        private Session $session,
        private CreatureRepository $creatures,
        private CreatureProfileBuilder $profileBuilder,
        private PlayerFinder $finder,
        private int $browseLimit,
        private int $minimumSearchLength,
    ) {
    }

    /**
     * Show the community page. "?tab=" chooses which half is on screen; anything
     * unrecognised (including nothing at all) shows the creatures.
     */
    public function show(Request $request): Response
    {
        $tab = $request->query('tab');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'creatures';
        }

        // Only the tab actually being shown does any work. The people tab must not
        // quietly run a creature query, and — more importantly — the creatures tab
        // must not quietly run a player search, which would slip past the
        // logged-in check below.
        if ($tab === 'users') {
            return $this->peopleTab($request);
        }

        return $this->creaturesTab();
    }

    /**
     * The creatures half: recently met PUBLIC creatures. Open to everyone,
     * logged in or not — it is the same list /browse has always shown.
     */
    private function creaturesTab(): Response
    {
        $summaries = $this->profileBuilder->summariesFor(
            $this->creatures->findRecentPublic($this->browseLimit)
        );

        return Response::html($this->templates->render('pages/community', [
            'tab' => 'creatures',
            'creatureSummaries' => $summaries,
            'search' => null,
            'minimumSearchLength' => $this->minimumSearchLength,
        ]));
    }

    /**
     * The people half: find a player by the start of their name. Logged-in only,
     * and every guard lives in PlayerFinder.
     */
    private function peopleTab(Request $request): Response
    {
        if (!is_int($this->session->get('user_id'))) {
            return Response::redirect('/login');
        }

        $search = $this->finder->find($request->query('q'), $request->clientIp());

        return Response::html($this->templates->render('pages/community', [
            'tab' => 'users',
            'creatureSummaries' => [],
            'search' => $search,
            'minimumSearchLength' => $this->minimumSearchLength,
        ]));
    }
}
