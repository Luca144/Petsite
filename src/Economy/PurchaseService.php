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
            // changed and we tell them.
            if (!$this->users->deductCurrency($userId, $item->price)) {
                $this->connection->rollBack();
                return PurchaseResult::failed('You don\'t have enough to buy ' . $item->name . '.');
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
