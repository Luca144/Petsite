<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Users\UserRepository;
use PDO;

/**
 * The rules for petting a creature (the core interaction).
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the one place that decides what happens when someone pets a
 * creature. If the same person has petted this creature too recently, it is
 * refused (the cooldown). Otherwise it records the pet, raises the creature's
 * happiness, grants it XP (which makes it grow), and — when the creature belongs
 * to somebody else — pays the PERSON DOING THE PETTING a gem.
 *
 * WHY THE PETTER IS PAID AND NOT THE OWNER (changed 2026-08-18). Paying the owner
 * rewarded standing still: your creatures earned while you did nothing. Paying the
 * visitor gives every player a reason to go and look at other people's creatures,
 * which is the loop this whole site is built around. The owner still gains — a
 * petted creature grows and gets happier — it is only the gems that moved.
 *
 * THE THREE THINGS THAT KEEP THIS FROM BEING FARMED (CLAUDE.md section 6 names
 * alt-account farming and currency duplication by name):
 *   1. PETTING YOUR OWN CREATURE NEVER PAYS. Checked below, on the server, from
 *      the creature's stored owner id — not from anything the browser sent.
 *   2. THE COOLDOWN. The same person cannot pet the same creature again for a
 *      while, so no single creature can be milked in a loop.
 *   3. A DAILY CAP per account. This is the one that answers alt accounts: an
 *      extra account is worth at most the cap per day, for real clicking effort,
 *      which is a bad trade. An honest player wandering around never reaches it.
 *
 * AND WHY THERE IS A TRANSACTION. Recording the pet and paying for it must happen
 * together or not at all, and the whole sequence has to be safe against the same
 * request arriving twice at once — see CreatureRepository::lockForPetting for the
 * full reasoning on that.
 *
 * The amounts, the cooldown and the cap all come from config, passed in as one
 * array so this class stays easy to read and to retune.
 */
final class PettingService
{
    /**
     * @param array{
     *     cooldown_seconds: int,
     *     happiness_per_pet: int,
     *     xp_per_pet: int,
     *     currency_per_pet: int,
     *     currency_daily_cap: int,
     *     currency_cap_window_seconds: int
     * } $pettingConfig
     */
    public function __construct(
        private PDO $connection,
        private PettingRepository $pettings,
        private CreatureRepository $creatures,
        private UserRepository $users,
        private array $pettingConfig,
        // The currency's display name, from config — never written in this file.
        // What the money is CALLED is content the Product Owner owns, and a
        // hardcoded word here is how a renamed currency ends up saying "coins" in
        // one message and "gems" everywhere else. ConfigSingleSourceTest guards it.
        private string $currencyName = 'coins',
    ) {
    }

    /**
     * Try to pet a creature on behalf of a user. Returns a PettingResult saying
     * whether it worked, with a message to show either way.
     */
    public function pet(int $actorUserId, Creature $creature): PettingResult
    {
        $this->connection->beginTransaction();

        try {
            // Take the creature's row for the rest of this transaction, so a second
            // copy of this same request waits here instead of racing us.
            $this->creatures->lockForPetting($creature->id);

            // The cooldown: the same person cannot pet the same creature again too
            // soon. Checked inside the lock, so it sees any pet that just landed.
            if ($this->pettings->wasPettedRecentlyBy(
                $actorUserId,
                $creature->id,
                $this->pettingConfig['cooldown_seconds']
            )) {
                $this->connection->commit();

                return PettingResult::onCooldown(
                    'You petted ' . $creature->name . ' recently — give them a little while.'
                );
            }

            // Record the pet, then apply its effects: more happiness and some XP.
            $this->pettings->record($creature->id, $actorUserId);
            $this->creatures->applyPetting(
                $creature->id,
                $this->pettingConfig['happiness_per_pet'],
                $this->pettingConfig['xp_per_pet']
            );

            $earned = $this->payPetter($actorUserId, $creature);

            $this->connection->commit();
        } catch (\Throwable $error) {
            // Something went wrong part-way: undo all of it and re-raise, rather
            // than leaving a pet recorded that was never paid for (or the reverse).
            $this->connection->rollBack();
            throw $error;
        }

        return PettingResult::success($this->successMessage($creature, $earned));
    }

    /**
     * Pay the petter, if this pet earns anything. Returns how many gems were
     * actually paid, which is zero for your own creature and zero once the day's
     * cap is reached.
     *
     * Called inside the transaction, and AFTER the new petting row exists — so the
     * count below includes the pet being paid for and the cap is exact rather than
     * one out.
     */
    private function payPetter(int $actorUserId, Creature $creature): int
    {
        // Your own creature never pays. Read from the creature's stored owner id,
        // which the browser has no say in.
        if ($actorUserId === $creature->ownerId) {
            return 0;
        }

        $perPet = $this->pettingConfig['currency_per_pet'];
        $cap = $this->pettingConfig['currency_daily_cap'];

        // What this account has already earned from petting in the window, this
        // pet included. If that is over the cap, this one is unpaid.
        $paidPetsInWindow = $this->pettings->countPaidPetsBy(
            $actorUserId,
            $this->pettingConfig['currency_cap_window_seconds']
        );

        if ($paidPetsInWindow * $perPet > $cap) {
            return 0;
        }

        $this->users->addCurrency($actorUserId, $perPet);

        return $perPet;
    }

    /**
     * The line shown after a successful pet. It always says what happened (golden
     * rule 4), and mentions the gems only when there were some — a player petting
     * their own creature should not be told about a reward they did not get.
     */
    private function successMessage(Creature $creature, int $earned): string
    {
        $base = 'You petted ' . $creature->name . '! They look happier.';

        if ($earned === 0) {
            return $base;
        }

        // "1 gem", not "1 gems": for the single case we trim a plural s off the
        // configured name. A crude rule, but it covers any English currency name
        // this site will plausibly use — and it is the same rule PurchaseService
        // uses, so the two never disagree.
        $word = $earned === 1 ? rtrim($this->currencyName, 's') : $this->currencyName;

        return $base . ' You earned ' . $earned . ' ' . $word . '.';
    }
}
