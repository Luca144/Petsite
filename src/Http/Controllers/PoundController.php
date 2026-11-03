<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\FreezeService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Pound\PoundRepository;
use Felkyo\Pound\PoundResult;
use Felkyo\Pound\PoundService;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * The pound (M3.1), and the two "keeping" actions on a creature's own page:
 * freezing at a stage and handing in at the pound (M3.2b).
 *
 *   GET  /pound                         the pound (public; adopting needs login)
 *   POST /pound/{id}/adopt              give a waiting creature a home
 *   POST /pound/{id}/reclaim            take your own back, within the grace period
 *   POST /creature/{id}/to-pound        hand a creature in (needs the "I'm sure" tick)
 *   POST /creature/{id}/freeze|unfreeze keep it at its stage / let it grow
 *
 * @package Felkyo\Http\Controllers
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md §7):
 * 1. Who may? Logged in, valid CSRF, and then the rule of each action, which
 *    PoundRepository/FreezeService enforce IN THEIR SQL (owner id or
 *    previous-owner id in the WHERE clause) — a forged creature id changes
 *    nothing that isn't yours.
 * 2. Worst case? Racing two adoptions (compare-and-swap: one wins), bouncing
 *    your own creature through the pound to farm traits (the previous owner
 *    can never adopt it), spamming actions (rate limit 'pound' per account).
 * 3. Tests: to be written after the birthday sprint (see decisions.md).
 */
final class PoundController
{
    /** @param array{max_attempts: int, window_seconds: int} $rateLimit */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private PoundRepository $pound,
        private PoundService $service,
        private FreezeService $freezer,
        private CreatureProfileBuilder $profiles,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        $userId = $this->session->get('user_id');

        return Response::html($this->templates->render('pages/pound', [
            'waiting' => $this->profiles->summariesFor($this->pound->available(60)),
            'settlingIn' => $this->pound->countSettlingIn(),
            'yours' => is_int($userId) ? $this->profiles->summariesFor($this->pound->reclaimableBy($userId)) : [],
            'graceDays' => $this->pound->graceDays(),
            'graceEndsAt' => fn (string $enteredAt): int => $this->pound->graceEndsAt($enteredAt),
            'isLoggedIn' => is_int($userId),
            'viewerId' => is_int($userId) ? $userId : null,
        ]));
    }

    public function adopt(Request $request, array $parameters): Response
    {
        return $this->act($request, '/pound', fn (int $userId, int $id) => $this->service->adopt($userId, $id), $parameters);
    }

    public function reclaim(Request $request, array $parameters): Response
    {
        return $this->act($request, '/pound', fn (int $userId, int $id) => $this->service->reclaim($userId, $id), $parameters);
    }

    public function toPound(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        // Nothing lost by accident (golden rule 9): the form carries an
        // "I'm sure" tick, and without it nothing happens.
        if ($request->input('sure') !== 'yes') {
            $this->session->flash('Tick "I\'m sure" first — the pound is a big step.');

            return Response::redirect('/creature/' . $creatureId);
        }

        return $this->act($request, '/pound', fn (int $userId, int $id) => $this->service->send($userId, $id), $parameters);
    }

    public function freeze(Request $request, array $parameters): Response
    {
        return $this->act($request, null, fn (int $userId, int $id) => $this->freezer->freeze($userId, $id), $parameters);
    }

    public function unfreeze(Request $request, array $parameters): Response
    {
        return $this->act($request, null, fn (int $userId, int $id) => $this->freezer->unfreeze($userId, $id), $parameters);
    }

    /**
     * Login, CSRF, rate limit, the action, flash. On success with a creature
     * id, go to that creature (a new home, a reclaimed friend); otherwise to
     * $backTo — or back to the creature's own page when $backTo is null.
     *
     * @param callable(int, int): PoundResult $action
     */
    private function act(Request $request, ?string $backTo, callable $action, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $backTo ??= '/creature/' . $creatureId;

        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }

        $bucket = 'user:' . $userId;
        if (!$this->rateLimiter->isAllowed('pound', $bucket, $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('That\'s a lot of comings and goings — give it a little while.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('pound', $bucket);

        $result = $action($userId, $creatureId);
        $this->session->flash($result->message);

        return Response::redirect($result->success && $result->creatureId !== null ? '/creature/' . $result->creatureId : $backTo);
    }
}
