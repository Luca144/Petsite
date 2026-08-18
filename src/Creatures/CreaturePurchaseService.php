<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

use Felkyo\Users\UserRepository;
use PDO;

/**
 * Buying a creature with gems.
 *
 * @package Felkyo\Creatures
 *
 * WHAT THIS IS: the one place that decides whether somebody may take home a new
 * creature, and carries it out. It replaces daily adoption, which handed out one
 * free creature every 24 hours — a mechanic whose only input was patience, and
 * which left gems with nothing interesting to be for.
 *
 * Now the loop closes: you earn gems by visiting other people's creatures, and
 * you spend them on one of your own. Earning and spending are two different
 * activities and both of them are the game.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6). This spends currency and
 * creates a record, so they are answered here before the code.
 *
 * 1. WHO IS ALLOWED? Any logged-in player who can afford it. There is no
 *    ownership question — you are buying something new, not touching anything
 *    that already belongs to somebody.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO?
 *    - Buy a species that is not for sale, by sending an id from elsewhere.
 *      findAdoptable() is the allow-list; anything not on it is refused.
 *    - Buy without paying, or pay less than the price. The price is read from the
 *      SPECIES ROW, never from the form — the browser sends only which species.
 *    - Send the same request twice at once to get two creatures for one payment.
 *      This is the duplication shape CLAUDE.md names. deductCurrency() carries
 *      the "AND currency_balance >= amount" condition that makes it safe: the
 *      second request finds the balance already spent, changes nothing, and
 *      returns false. The creature is only created after a successful deduction,
 *      inside the same transaction, so there is no window between paying and
 *      receiving.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? CreaturePurchaseServiceTest proves each: a
 *    species not for sale is refused, too few gems is refused with the exact gap
 *    named, the balance can never go negative, and buying twice with only enough
 *    for one yields exactly one creature.
 *
 * WHAT IS DELIBERATELY NOT HERE: a cap on how many creatures somebody may own.
 * Gems are the limit, and they are a kind one — you can always earn more. A rule
 * saying "you have enough creatures now" would be the site telling somebody they
 * have cared about enough things, which is not a sentence it should ever say.
 */
final class CreaturePurchaseService
{
    /**
     * @param string[] $creatureNames The pool of friendly default names (config).
     */
    public function __construct(
        private PDO $connection,
        private SpeciesRepository $species,
        private CreatureRepository $creatures,
        private UserRepository $users,
        private array $creatureNames,
        // The currency's display name, from config — never written in this file.
        private string $currencyName = 'coins',
    ) {
    }

    /**
     * The species a player may buy, cheapest first — so the reachable ones are
     * the ones they see when they arrive.
     *
     * @return Species[]
     */
    public function forSale(): array
    {
        $forSale = $this->species->findAdoptable();

        usort($forSale, static fn (Species $a, Species $b): int => $a->gemPrice <=> $b->gemPrice);

        return $forSale;
    }

    /**
     * Buy one creature of $speciesId for $userId.
     */
    public function buy(int $userId, int $speciesId): CreaturePurchaseResult
    {
        // The allow-list. A species that is not for sale is not for sale however
        // the id arrived, and the PRICE comes from this row rather than the form.
        $species = null;
        foreach ($this->species->findAdoptable() as $candidate) {
            if ($candidate->id === $speciesId) {
                $species = $candidate;
                break;
            }
        }

        if ($species === null) {
            return CreaturePurchaseResult::refused('There are none of those to be had here.');
        }

        $this->connection->beginTransaction();

        try {
            // Take the gems only if they are there. The condition lives inside the
            // UPDATE (see UserRepository::deductCurrency), so a balance can never
            // go negative and two simultaneous purchases cannot both succeed on
            // one payment's worth of gems.
            if (!$this->users->deductCurrency($userId, $species->gemPrice)) {
                $this->connection->rollBack();

                return CreaturePurchaseResult::refused($this->cannotAffordMessage($userId, $species));
            }

            $creature = $this->creatures->create($userId, $species->id, $this->pickName());

            $this->connection->commit();
        } catch (\Throwable $error) {
            // Never leave somebody charged with no creature, or the reverse.
            $this->connection->rollBack();
            throw $error;
        }

        return CreaturePurchaseResult::bought(
            $creature,
            $creature->name . ' the ' . $species->name . ' comes home with you!'
        );
    }

    /**
     * Say exactly how short somebody is, and where more gems come from.
     *
     * Golden rules 3 and 5: name the gap in plain words rather than saying
     * "insufficient funds", and never leave a dead end — a player with an empty
     * purse has no way of knowing how gems arrive unless somebody tells them.
     */
    private function cannotAffordMessage(int $userId, Species $species): string
    {
        $balance = $this->users->findById($userId)?->currencyBalance ?? 0;
        $shortBy = $species->gemPrice - $balance;

        // "1 more gem", not "1 more gems". The same rule PurchaseService uses.
        $word = $shortBy === 1 ? rtrim($this->currencyName, 's') : $this->currencyName;

        return 'You need ' . $shortBy . ' more ' . $word . ' for a ' . $species->name
            . " — you earn them by petting other players' creatures.";
    }

    /**
     * A friendly default name from the config pool. The player can rename their
     * creature the moment they get it, so this only has to be pleasant, not right.
     */
    private function pickName(): string
    {
        return $this->creatureNames[array_rand($this->creatureNames)];
    }
}
