<?php

declare(strict_types=1);

namespace Felkyo\Economy;

use Felkyo\Users\UserRepository;
use PDO;

/**
 * The rules for buying an item from a shop.
 *
 * @package Felkyo\Economy
 *
 * WHAT THIS IS: the one place that decides whether a purchase is allowed and
 * carries it out. The flow is deliberately generic — a shop has items, an item has
 * a price, buying validates the balance, deducts it, and grants the item — so
 * adding more shops or items later is a data change, not new logic.
 *
 * WHY IT USES A TRANSACTION: deducting currency and adding the item must happen
 * together — either both, or neither. We wrap them in a database transaction so a
 * failure half-way cannot leave a player charged with nothing to show for it. The
 * deduction itself refuses to go below zero (see UserRepository::deductCurrency),
 * so a balance can never go negative.
 */
final class PurchaseService
{
    public function __construct(
        private PDO $connection,
        private ShopRepository $shops,
        private UserRepository $users,
        private InventoryRepository $inventory,
        // The currency's display name, from config — same convention as
        // ItemDisposalService, so a renamed currency never leaves a stale word
        // in a message.
        private string $currencyName = 'coins',
    ) {
    }

    /**
     * Buy one of $itemId from shop $shopId, for user $userId.
     */
    public function buy(int $userId, int $shopId, int $itemId): PurchaseResult
    {
        // Confirm the shop really sells this item, and get its real price.
        $item = $this->shops->findSoldItem($shopId, $itemId);
        if ($item === null) {
            return PurchaseResult::failed('That item is not for sale here.');
        }

        $this->connection->beginTransaction();
        try {
            // Take the money only if the player can afford it. If not, nothing is
            // changed and we tell them — and the message follows the golden rules:
            // say the exact gap in plain words ("you need 3 more", not "insufficient
            // funds"), and never leave a dead end — name the way gems actually
            // arrive, because a brand-new player has zero and no way to know.
            if (!$this->users->deductCurrency($userId, $item->price)) {
                $this->connection->rollBack();

                $balance = $this->users->findById($userId)?->currencyBalance ?? 0;
                $shortBy = $item->price - $balance;

                // "1 more coin", not "1 more coins": for the one-short case we
                // trim a plural s off the configured name. A crude rule, but it
                // covers any English currency name this site will plausibly use.
                $currencyWord = $shortBy === 1 ? rtrim($this->currencyName, 's') : $this->currencyName;

                return PurchaseResult::failed(
                    'You need ' . $shortBy . ' more ' . $currencyWord . ' for the ' . $item->name
                    // Where gems come from, as of 2026-08-18: you earn them by
                    // petting OTHER people's creatures. This sentence has to keep
                    // matching PettingService — it is the only place a new player
                    // is told how to get any, so a stale answer here is a dead end.
                    . ' — you earn ' . $this->currencyName . " by petting other players' creatures."
                );
            }

            // Give them the item, then commit both changes together.
            $this->inventory->addItem($userId, $item->id);
            $this->connection->commit();
        } catch (\Throwable $error) {
            // Something went wrong mid-purchase: undo everything and re-raise.
            $this->connection->rollBack();
            throw $error;
        }

        return PurchaseResult::success($item, 'You bought ' . $item->name . '!');
    }
}
