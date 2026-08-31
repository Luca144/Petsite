<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

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
 * The species list: every creature kind the site has, and the door to a new one.
 *
 * @package Felkyo\Http\Controllers
 *
 * One card per species — its baby sprite, its status as a WORD ("draft" /
 * "published", never colour alone), and, for a draft, exactly what it still
 * needs before it may publish. Clicking a card opens its one-page editor.
 * Web glue only; the rules live in SpeciesEditorService.
 */
final class AdminCreaturesController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private SpeciesRepository $species,
        private SpeciesEditorService $editor,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
        /** @var string[] from config growth.stage_start_levels keys. */
        private array $stageNames,
    ) {
    }

    /**
     * The list. Drafts and published together, each honestly labelled —
     * the artist's to-do list is visible at a glance.
     */
    public function show(Request $request): Response
    {
        $cards = [];
        foreach ($this->species->all() as $species) {
            $cards[] = [
                'species' => $species,
                // What still blocks publishing — the card says it, so the
                // list doubles as "what's unfinished" for creatures.
                'missing' => $species->isPublished() ? [] : $this->editor->missingForPublish($species),
            ];
        }

        return Response::html($this->templates->render('pages/admin-creatures', [
            'cards' => $cards,
            'stageNames' => $this->stageNames,
        ]));
    }

    /**
     * Start a new species: a name in, its editor page out.
     */
    public function create(Request $request): Response
    {
        $actor = $this->currentUser();

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/creatures');
        }

        if (!$this->rateLimiter->isAllowed(
            'species_save',
            'user:' . $actor->id,
            $this->saveRateLimit['max_attempts'],
            $this->saveRateLimit['window_seconds']
        )) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect('/admin/creatures');
        }
        $this->rateLimiter->record('species_save', 'user:' . $actor->id);

        $result = $this->editor->create($actor, $request->input('name'), $request->clientIp());
        $this->session->flash($result->message);

        // Straight into the new species' page — the next thing the artist
        // does is fill it in, so that is where the door leads.
        return Response::redirect(
            $result->success && $result->speciesId !== null
                ? '/admin/creatures/' . $result->speciesId
                : '/admin/creatures'
        );
    }

    private function currentUser(): User
    {
        $user = $this->users->findById((int) $this->session->get('user_id'));
        if ($user === null) {
            throw new \RuntimeException('The species list was reached without a logged-in user.');
        }

        return $user;
    }
}
