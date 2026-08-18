<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Economy\InventoryRepository;
use PDO;

/**
 * Giving one of your creatures something to eat.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the one place that decides whether a treat may be given and what
 * it does. A treat is taken out of the player's things and turned into happiness
 * and rest for the creature — the second way to care for one, and the reason gems
 * are worth earning.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6). This moves an owned thing
 * and changes another owned thing, so they are answered here before the code.
 *
 * 1. WHO IS ALLOWED? The creature's owner, feeding an item they hold. Both halves
 *    are enforced twice over. The creature's owner id is compared here, AND
 *    InventoryRepository::removeOne() takes the user id in its WHERE clause, so
 *    the database refuses to take a treat out of somebody else's things whatever
 *    this class forgets. You cannot feed a creature that is not yours: that would
 *    let anybody change the state of anybody's creature, and there is no version
 *    of that which is worth the gift mechanic it would enable.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO?
 *    - Feed a treat they do not own, by sending an item id they saw elsewhere.
 *      removeOne() matches nothing and returns false; nothing is applied.
 *    - Feed somebody else's creature by changing the id in the address. Refused
 *      by the ownership check, with the same wording as a creature that does not
 *      exist, so the two are indistinguishable from outside.
 *    - Send the same request twice at once, to get two treats' worth of mood for
 *      one treat — or, worse, to have one treat removed but two applied. This is
 *      the currency-duplication shape CLAUDE.md names. It is closed twice: the
 *      whole thing runs in a transaction that takes the creature's row first, so
 *      the two requests queue, and removeOne() only succeeds for a row that still
 *      has something in it. The second request finds nothing and is refused.
 *    - Feed something that is not food (a sticker) hoping for an effect. Refused
 *      before anything is taken, so nothing is lost by trying.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? FeedingServiceTest proves each refusal:
 *    somebody else's creature, an item not owned, a non-treat, and — the one that
 *    matters most — that a treat fed twice is only ever consumed once.
 *
 * WHAT IS DELIBERATELY NOT HERE: any way for feeding to fail badly. A creature
 * already at full happiness still accepts a treat and still says thank you; the
 * numbers simply do not move. Refusing that would mean punishing somebody for
 * looking after their creature well, which is the opposite of the point.
 */
final class FeedingService
{
    public function __construct(
        private PDO $connection,
        private CreatureRepository $creatures,
        private InventoryRepository $inventory,
        private MoodCalculator $mood,
    ) {
    }

    /**
     * Feed one of $itemId to $creature, on behalf of $actorUserId.
     */
    public function feed(int $actorUserId, Creature $creature, int $itemId): FeedingResult
    {
        // You feed your OWN creatures. Read from the creature's stored owner id,
        // which the browser has no say in.
        if ($creature->ownerId !== $actorUserId) {
            return FeedingResult::refused(
                'You can only feed your own creatures. ' . $creature->name . ' belongs to somebody else.'
            );
        }

        // Does this player hold one, and is it even food? Checked before anything
        // is taken, so a mistaken tap costs nothing.
        $stack = $this->inventory->findStackForUser($actorUserId, $itemId);
        if ($stack === null) {
            return FeedingResult::refused(
                'You do not have one of those. Treats are sold at the village store, and turn up while exploring.'
            );
        }

        if (!$stack->item->isTreat()) {
            return FeedingResult::refused(
                $creature->name . ' had a sniff at the ' . $stack->item->name . ' and left it. Try a treat instead.'
            );
        }

        $this->connection->beginTransaction();

        try {
            // Queue behind any other request touching this creature, so two taps
            // cannot both apply. Same lock petting uses, for the same reason.
            $this->creatures->lockForPetting($creature->id);

            // Take the treat FIRST, and believe the answer. removeOne() returns
            // false when there was nothing left to take, which is exactly what the
            // second of two racing requests sees. Applying the mood before this,
            // or ignoring the answer, would hand out the effect for free.
            if (!$this->inventory->removeOne($actorUserId, $itemId)) {
                $this->connection->rollBack();

                return FeedingResult::refused(
                    'That was your last ' . $stack->item->name . ', and it is already gone.'
                );
            }

            $this->applyTreat($creature, $stack->item->happinessBonus, $stack->item->energyBonus);

            $this->connection->commit();
        } catch (\Throwable $error) {
            // Never leave a treat eaten with no effect, or an effect with no treat.
            $this->connection->rollBack();
            throw $error;
        }

        return FeedingResult::success(
            $creature->name . ' enjoyed the ' . $stack->item->name . '!'
        );
    }

    /**
     * Apply a treat's kindness to the creature.
     *
     * Both readings are aged first, for the same reason petting ages them: a
     * creature nobody has visited for a week has drifted, and a treat should top
     * up from where it actually is rather than from a number written last Tuesday.
     */
    private function applyTreat(Creature $creature, int $happinessBonus, int $energyBonus): void
    {
        $mood = $this->mood->moodFor(
            $creature->happiness,
            $creature->happinessAgeSeconds,
            $creature->energy,
            $creature->energyAgeSeconds
        );

        $this->creatures->saveMood(
            $creature->id,
            $this->mood->afterGaining($mood->happiness, $happinessBonus),
            $this->mood->afterResting($mood->energy, $energyBonus)
        );
    }
}
