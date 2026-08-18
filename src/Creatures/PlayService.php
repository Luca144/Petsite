<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use PDO;

/**
 * Playing a game with one of your creatures.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the rules for a round. It starts one (choosing a game and hiding
 * an answer), and it judges one (comparing a guess, cheering the creature up,
 * remembering when). The ANSWER ITSELF is handed back to the caller to keep,
 * because keeping it is a session concern and this class knows nothing about HTTP.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6). This changes an owned thing
 * and hands out a reward, so they are answered here before the code.
 *
 * 1. WHO IS ALLOWED? The creature's owner. Checked here from the creature's stored
 *    owner id, and again by the controller before it will even start a round.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO?
 *
 *    - CLAIM A WIN WITHOUT PLAYING. This is the whole reason the games are guesses
 *      rather than arcade games. A game running in the browser cannot be trusted to
 *      report its own result: whatever "I won" the page sends, anybody can send. So
 *      the answer never leaves the server, and a submitted guess is compared against
 *      it. A cheat has to guess right, which is playing.
 *
 *    - RETRY UNTIL THEY WIN. The caller clears the round when it submits the guess,
 *      so one round takes exactly one answer. Re-posting the same guess finds no
 *      round and is told to start a new game. That is why judge() takes the answer
 *      as an argument rather than reading it: this class cannot forget to clear
 *      something it never held.
 *
 *    - ANSWER A ROUND OPENED FOR A DIFFERENT CREATURE, to line up an easy game
 *      against a creature on cooldown. The controller checks that the round belongs
 *      to this creature before it gets here.
 *
 *    - PLAY IN A LOOP FOR FREE HAPPINESS, which would make treats — bought with
 *      gems — pointless. A per-creature cooldown limits the REWARD. It does not
 *      limit playing: a creature on cooldown still plays and still enjoys it.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? PlayServiceTest proves each: a wrong guess is
 *    not punished, somebody else's creature is refused, the cooldown stops the bonus
 *    without stopping the game, and happiness never falls as a result of playing.
 */
final class PlayService
{
    /**
     * @param array{
     *     cooldown_seconds: int,
     *     happiness_on_win: int,
     *     happiness_on_loss: int,
     *     energy_per_play: int
     * } $playConfig
     */
    public function __construct(
        private PDO $connection,
        // The writes a game makes: the row lock, the mood, the play cooldown.
        // Their own class because each must run inside the transaction below —
        // read CreatureInteractions before changing any of this.
        private CreatureInteractions $interactions,
        private PlayableGames $games,
        private MoodCalculator $mood,
        private array $playConfig,
    ) {
    }

    /**
     * Begin a round: choose a game, and hide an answer in it.
     *
     * Returns the game's slug and the index of the right choice. The CALLER keeps
     * the answer somewhere the browser cannot read (the session) and hands it back
     * to judge() later.
     *
     * @return array{slug: string, answer: int}
     */
    public function startRound(): array
    {
        $slug = $this->games->slugForRoll(random_int(0, count($this->games->slugs()) - 1));
        $choices = $this->games->choiceCountOf($slug);

        return [
            'slug' => $slug,
            // random_int, not rand(): this is the one number a player must not be
            // able to predict, and a predictable answer turns the game into a
            // reward button. PHP's cryptographic source costs nothing here.
            'answer' => random_int(0, max(0, $choices - 1)),
        ];
    }

    /**
     * Judge a guess against the answer the caller kept, and cheer the creature up.
     *
     * @param string $slug   The game the round was for.
     * @param int    $answer The right choice, from startRound().
     * @param int    $guess  What the player picked.
     */
    public function judge(int $actorUserId, Creature $creature, string $slug, int $answer, int $guess): PlayResult
    {
        if ($creature->ownerId !== $actorUserId) {
            return PlayResult::noRound(
                'You can only play with your own creatures.'
            );
        }

        if (!$this->games->has($slug)) {
            // The game was removed from config while this round was open. Nobody's
            // fault, and not worth an error page.
            return PlayResult::noRound('That game has finished. Start another?');
        }

        $won = $guess === $answer;

        $this->connection->beginTransaction();

        try {
            // Queue behind anything else touching this creature, so two submissions
            // cannot both apply. The same lock petting and feeding take.
            $this->interactions->lockForInteraction($creature->id);

            // The cooldown decides whether there is a BONUS, never whether there is
            // a game. Checked inside the lock so two quick submissions cannot both
            // find the cooldown clear.
            $rewarded = !$this->interactions->playedRecently(
                $creature->id,
                $this->playConfig['cooldown_seconds']
            );

            if ($rewarded) {
                $this->applyMood($creature, $won);
                $this->interactions->markPlayed($creature->id);
            }

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        $line = $this->games->outcomeLine($slug, $creature->name, $won);

        // A creature that has played very recently says the same warm thing, plus a
        // quiet note that it is worn out — rather than a refusal, which is what
        // "come back later" would be.
        if (!$rewarded) {
            $line .= ' ' . $creature->name . ' has had plenty of games just now, and is happy either way.';
        }

        return $won ? PlayResult::won($line) : PlayResult::lost($line);
    }

    /**
     * Cheer the creature up for having been played with, and tire it a little.
     *
     * A LOSS STILL CHEERS IT UP. Guessing wrong is not a failure: somebody came and
     * played with it either way, which is the thing that mattered. The win is worth
     * more, and that is the whole difference.
     *
     * Both readings are aged first, for the same reason petting and feeding age
     * them — a creature nobody has visited for a week has drifted, and a game should
     * top up from where it actually is.
     */
    private function applyMood(Creature $creature, bool $won): void
    {
        $mood = $this->mood->moodFor(
            $creature->happiness,
            $creature->happinessAgeSeconds,
            $creature->energy,
            $creature->energyAgeSeconds
        );

        $gain = $won
            ? $this->playConfig['happiness_on_win']
            : $this->playConfig['happiness_on_loss'];

        $this->interactions->saveMood(
            $creature->id,
            $this->mood->afterGaining($mood->happiness, $gain),
            $this->mood->afterSpending($mood->energy, $this->playConfig['energy_per_play'])
        );
    }
}
