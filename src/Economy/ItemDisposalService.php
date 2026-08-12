<?php

declare(strict_types=1);

namespace Felkyo\Economy;

use Felkyo\Users\UserRepository;
use PDO;

/**
 * The rules for parting with an item — selling it, or throwing it away.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: the one place that decides whether a player may let go of one of
 * their things, and carries it out. Selling turns an item into currency;
 * discarding turns it into nothing. Both are the same shape underneath — take
 * exactly one away, and if that worked, do the consequence.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6), answered before this was
 * written, because this touches both somebody's belongings and their money:
 *
 * 1. WHO IS ALLOWED, AND HOW IS IT ENFORCED? Only the owner, and the enforcement
 *    is InventoryRepository::removeOne(), which names the acting player in its
 *    WHERE clause. There is no code path here that acts on an item without the
 *    player's own id being part of the lookup, so "the button wasn't shown" is
 *    never what is protecting anybody. A request arriving directly, with somebody
 *    else's item id typed in, finds nothing and is refused.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO?
 *    - Sell an item they do not own, by changing the id in the form. Closed by
 *      the owner-scoped lookup above.
 *    - Be paid twice for one item, by firing the request twice at once. This is
 *      the real danger, because it makes currency out of nothing and it happens
 *      by accident too — a double-tapped button on a slow phone does it. Closed
 *      by taking the item away FIRST, conditionally, and only paying if that
 *      actually removed something. See the long note in removeOne().
 *    - Be paid, then have the payment fail, or lose the item without being paid.
 *      Closed by the transaction: both changes commit together or neither does.
 *    - Sell something worth more than it costs, in a loop. Closed upstream, by
 *      the shop margin rule in config and the test that guards it.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? tests/Integration/ItemDisposalServiceTest
 *    proves each refusal explicitly: a stranger is refused, an unsellable item is
 *    refused, an empty pile is refused, and — the important one — two sales of a
 *    single item pay exactly once, with the balance and the inventory agreeing
 *    afterwards.
 *
 * WHY THE ITEM GOES FIRST AND THE MONEY SECOND. It would read more naturally to
 * pay the player and then take the item. It would also be the wrong order: if the
 * removal then failed, they would have been paid for something they still own.
 * Taking the item first means the worst case is an item removed and a payment
 * rolled back — and the transaction prevents even that.
 */
final class ItemDisposalService
{
    public function __construct(
        private PDO $connection,
        private UserRepository $users,
        private InventoryRepository $inventory,
        // What the currency is called. From config, because the name is content —
        // and because it was hardcoded as "gems" here while the rest of the site
        // said "coins", which is exactly the kind of small wrongness that makes a
        // place feel unfinished.
        private string $currencyName = 'coins',
    ) {
    }

    /**
     * Sell one of an item, paying the player its sell value.
     */
    public function sell(int $userId, int $itemId): DisposalResult
    {
        // Look the pile up as this player's — not as "item 7", which would be
        // anybody's. If they do not own one, there is nothing more to consider.
        $stack = $this->inventory->findStackForUser($userId, $itemId);
        if ($stack === null) {
            return DisposalResult::refused('You don\'t have one of those to sell.');
        }

        // An item worth nothing is not sold for nothing — it is refused, with the
        // reason said out loud. Golden rule 3: a refusal names what is going on
        // rather than leaving somebody staring at a button that did nothing.
        if (!$stack->item->isSellable()) {
            return DisposalResult::refused(
                'No shop around here would buy a ' . $stack->item->name . '. You could keep it, or throw it away.'
            );
        }

        $earned = $stack->item->sellValue;

        $this->connection->beginTransaction();
        try {
            // Take the item away first, and only continue if that really removed
            // one. If a second identical request is racing this one, exactly one
            // of them gets true here and the other gets false.
            if (!$this->inventory->removeOne($userId, $itemId)) {
                $this->connection->rollBack();
                return DisposalResult::refused('You don\'t have one of those to sell.');
            }

            $this->users->addCurrency($userId, $earned);
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        return DisposalResult::sold(
            'You sold ' . $stack->item->name . ' for ' . $earned . ' ' . $this->currencyName . '.',
            $earned
        );
    }

    /**
     * Throw one of an item away, for nothing.
     *
     * There is no transaction here because there is only one change to make, and
     * a transaction around a single statement protects nothing. The owner-scoped,
     * conditional removal is doing all the work.
     */
    public function discard(int $userId, int $itemId): DisposalResult
    {
        $stack = $this->inventory->findStackForUser($userId, $itemId);
        if ($stack === null) {
            return DisposalResult::refused('You don\'t have one of those to throw away.');
        }

        if (!$this->inventory->removeOne($userId, $itemId)) {
            return DisposalResult::refused('You don\'t have one of those to throw away.');
        }

        return DisposalResult::discarded('You threw away ' . $stack->item->name . '.');
    }
}
