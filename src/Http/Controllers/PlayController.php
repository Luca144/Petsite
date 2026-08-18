<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\PlayableGames;
use Felkyo\Creatures\PlayService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use League\Plates\Engine;

/**
 * Playing a game with one of your creatures.
 *
 * @package Felkyo\Http\Controllers
 *
 * TWO HALVES. show() starts a round: it picks a game, hides the answer in the
 * SESSION, and draws the choices. answer() judges a guess against that hidden
 * answer, clears the round, and sends you back with the result.
 *
 * WHY THE ANSWER LIVES IN THE SESSION AND NOWHERE ELSE. It is the one value a
 * player must not be able to see or predict — the moment it reaches the page in any
 * form, the game becomes a button that says "I won". So it is never rendered, never
 * put in a hidden field, and never signed and handed out. The page knows only how
 * many choices there are.
 *
 * WHY THE ROUND IS CLEARED BEFORE IT IS JUDGED. One round takes exactly one
 * answer. Clearing first means a re-posted guess — a refresh, a double tap, or
 * somebody retrying until they win — finds nothing to answer and is invited to
 * start a new game. Clearing AFTER judging would leave a window where the same
 * round could be answered twice, and the second attempt would know the first
 * attempt's outcome.
 *
 * The round also records WHICH CREATURE it was opened for, so a round started on an
 * easy game cannot be submitted against a different creature.
 */
final class PlayController
{
    /** Where the open round lives in the session. */
    private const ROUND_KEY = 'play_round';

    /**
     * @param array{max_attempts: int, window_seconds: int} $rateLimit
     */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private CreatureRepository $creatures,
        private PlayService $play,
        private PlayableGames $games,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    /**
     * Start a round and draw it.
     *
     * @param array<string, string> $parameters Captured route parameters.
     */
    public function show(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);

        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        $creature = $this->creatures->findById($creatureId);
        if ($creature === null || $creature->ownerId !== $userId) {
            // The same answer for "no such creature" and "not yours", so nothing is
            // revealed about whose it is.
            return Response::redirect('/');
        }

        // A fresh round, replacing any half-finished one. Opening a new game is a
        // deliberate act, so abandoning the previous round is what somebody means.
        $round = $this->play->startRound();
        $this->session->set(self::ROUND_KEY, [
            'creature_id' => $creature->id,
            'slug' => $round['slug'],
            'answer' => $round['answer'],
        ]);

        return Response::html($this->templates->render('pages/play', [
            'creature' => $creature,
            'game' => $this->games->presentation($round['slug'], $creature->name),
        ]));
    }

    /**
     * Judge a guess.
     *
     * @param array<string, string> $parameters Captured route parameters.
     */
    public function answer(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $creaturePath = '/creature/' . $creatureId;

        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($creaturePath);
        }

        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return Response::redirect('/');
        }

        // A light limit on how fast one address can submit guesses. The real gates
        // are the hidden answer and the cooldown; this is the standard anti-script
        // limit every state-changing route carries (CLAUDE.md section 6).
        if (!$this->rateLimiter->isAllowed(
            'play',
            $request->clientIp(),
            $this->rateLimit['max_attempts'],
            $this->rateLimit['window_seconds']
        )) {
            $this->session->flash('That is a lot of games at once — give it a moment.');
            return Response::redirect($creaturePath);
        }
        $this->rateLimiter->record('play', $request->clientIp());

        // TAKE THE ROUND AND CLEAR IT, BEFORE JUDGING. See the class docblock: this
        // is what makes one round worth exactly one answer.
        $round = $this->session->get(self::ROUND_KEY);
        $this->session->remove(self::ROUND_KEY);

        if (!is_array($round) || ($round['creature_id'] ?? null) !== $creature->id) {
            $this->session->flash('That game has finished. Start a new one?');
            return Response::redirect($creaturePath);
        }

        $result = $this->play->judge(
            $userId,
            $creature,
            (string) $round['slug'],
            (int) $round['answer'],
            (int) $request->input('choice')
        );

        $this->session->flash($result->message());

        // The celebration plays for a LOSS too. Being played with is the good thing
        // that happened; guessing right is a bonus on top of it.
        if ($result->played()) {
            $this->session->celebrate('pet');
        }

        return Response::redirect($creaturePath);
    }
}
