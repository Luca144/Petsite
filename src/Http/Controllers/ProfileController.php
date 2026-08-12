<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\ProfileService;
use League\Plates\Engine;

/**
 * A player's page: the one other people visit, and the form for changing it.
 *
 * @package Felkyo\Http\Controllers
 *
 * WHO MAY DO WHAT: anybody may look at a profile, including somebody not logged
 * in — that is the point of having one. Only the player themselves may change
 * theirs, and "themselves" always comes from the session, never from the request.
 * There is no parameter anywhere in this class saying whose profile to edit.
 *
 * WHAT A VISITOR SEES IS WHAT THE OWNER SEES. The page is identical either way,
 * apart from an "edit your page" link and a quiet note about hidden creatures.
 * The build plan asks that players be shown clearly what others can see, and the
 * simplest way to be honest about that is for the page to be the same page.
 */
final class ProfileController
{
    /**
     * @param array{max_about_length: int, max_featured_creatures: int} $limits
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private ProfileRepository $profiles,
        private ProfileService $profileService,
        private CreatureRepository $creatures,
        private CreatureProfileBuilder $creatureProfiles,
        private AvatarSet $avatars,
        private RateLimiter $rateLimiter,
        private array $limits,
        private array $rateLimit,
    ) {
    }

    /**
     * Somebody's page, found by their name.
     *
     * @param array<string, string> $parameters
     */
    public function show(Request $request, array $parameters): Response
    {
        $profile = $this->profiles->findByUsername((string) ($parameters['username'] ?? ''));

        if ($profile === null) {
            return Response::html(
                $this->templates->render('pages/profile-not-found'),
                404
            );
        }

        $viewerId = $this->session->get('user_id');
        $isOwner = is_int($viewerId) && $viewerId === $profile->id;

        $creatures = $this->creatures->findForProfile($profile->id, 12);

        return Response::html($this->templates->render('pages/profile', [
            'profile' => $profile,
            'avatarPath' => $this->avatars->imagePathFor($profile->avatarKey),
            'avatarName' => $this->avatars->nameFor($profile->avatarKey),
            'summaries' => $this->creatureProfiles->summariesFor($creatures),
            'isOwner' => $isOwner,
            // Only the owner is told this, and only so their own page never feels
            // as though it has lost something. A visitor has no business knowing
            // how many creatures somebody keeps out of sight.
            'hiddenCount' => $isOwner ? $this->creatures->countPrivateForOwner($profile->id) : 0,
            'flash' => $this->session->takeFlash(),
        ]));
    }

    /**
     * The form for changing your own page.
     */
    public function edit(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        $profile = $this->profiles->findById($userId);
        if ($profile === null) {
            // The session names a user who no longer exists — treat it as logged
            // out rather than carrying on with half an identity.
            return Response::redirect('/login');
        }

        // Every creature they own, so they can choose which to show off. Featured
        // ones first, so the current arrangement is visible at a glance.
        $owned = $this->creatures->findByOwner($userId);

        return Response::html($this->templates->render('pages/profile-edit', [
            'profile' => $profile,
            'avatars' => $this->avatars->all(),
            'summaries' => $this->creatureProfiles->summariesFor($owned),
            'featuredIds' => $this->creatures->findFeaturedIds($userId),
            'maxAboutLength' => $this->limits['max_about_length'],
            'maxFeatured' => $this->limits['max_featured_creatures'],
            'flash' => $this->session->takeFlash(),
        ]));
    }

    /**
     * Save the changes. Appearance and featured creatures arrive together,
     * because they are one form and one "Save" button — a player should not have
     * to save twice to change two things on the same screen.
     */
    public function save(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect('/profile/edit');
        }

        if (!$this->rateLimiter->isAllowed('profile', $request->clientIp(), $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('You are saving a little too fast — please slow down.');
            return Response::redirect('/profile/edit');
        }
        $this->rateLimiter->record('profile', $request->clientIp());

        $appearance = $this->profileService->saveAppearance(
            $userId,
            (string) $request->input('avatar_key'),
            (string) $request->input('about')
        );

        // If the appearance was refused, stop and say why. Saving half a form and
        // reporting only one of two outcomes would leave somebody unsure what
        // actually happened.
        if (!$appearance->isSuccessful()) {
            $this->session->flash($appearance->message());
            return Response::redirect('/profile/edit');
        }

        $featured = $this->profileService->saveFeatured($userId, $this->submittedCreatureIds($request));

        $this->session->flash(
            $featured->isSuccessful() ? 'Your page has been saved.' : $featured->message()
        );

        return Response::redirect('/profile/edit');
    }

    /**
     * The creature ids the form sent, as plain integers in the order given.
     *
     * Anything that is not a number becomes 0, which owns nothing and is dropped
     * by the service. We do not try to interpret odd input here — we only make
     * sure that what leaves this method is a list of integers.
     *
     * @return array<int, int>
     */
    private function submittedCreatureIds(Request $request): array
    {
        return array_map(
            static fn (string $value): int => (int) $value,
            $request->inputList('featured')
        );
    }
}
