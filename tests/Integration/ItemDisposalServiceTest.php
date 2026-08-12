<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ItemDisposalService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Users\UserRepository;

/**
 * Tests for selling and discarding — the money side of owning things.
 *
 * @package Felkyo\Tests\Integration
 *
 * Most of these are written from the attacker's side, because CLAUDE.md asks for
 * proof that the bad case is REFUSED rather than only that the good case works.
 * The good case is here too, but it is the least interesting test in the file.
 *
 * The scenario throughout: Mira owns things, Rowan does not, and Rowan tries.
 */
final class ItemDisposalServiceTest extends DatabaseTestCase
{
    private ItemDisposalService $disposal;
    private InventoryRepository $inventory;
    private UserRepository $users;
    private int $miraId;
    private int $rowanId;
    private int $itemId;
    private int $itemSellValue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $this->users = new UserRepository($this->connection);
        $this->inventory = new InventoryRepository($this->connection);
        $this->disposal = new ItemDisposalService($this->connection, $this->users, $this->inventory);

        $this->miraId = $this->users->create('mira', 'mira@example.com', 'hash')->id;
        $this->rowanId = $this->users->create('rowan', 'rowan@example.com', 'hash')->id;

        $shops = new ShopRepository($this->connection);
        $item = $shops->findItems($shops->findBySlug('general-store')->id)[0];
        $this->itemId = $item->id;
        $this->itemSellValue = $item->sellValue;
    }

    private function balanceOf(int $userId): int
    {
        return $this->users->findById($userId)->currencyBalance;
    }

    public function testSellingOnePaysItsSellValueAndTakesTheItem(): void
    {
        $this->inventory->addItem($this->miraId, $this->itemId);
        $this->inventory->addItem($this->miraId, $this->itemId);
        $before = $this->balanceOf($this->miraId);

        $result = $this->disposal->sell($this->miraId, $this->itemId);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame($this->itemSellValue, $result->currencyEarned());
        $this->assertSame($before + $this->itemSellValue, $this->balanceOf($this->miraId));
        $this->assertSame(1, $this->inventory->findStackForUser($this->miraId, $this->itemId)->quantity);
    }

    public function testSellingTheLastOneLeavesNoEmptyPileBehind(): void
    {
        $this->inventory->addItem($this->miraId, $this->itemId);

        $this->disposal->sell($this->miraId, $this->itemId);

        // Not "a pile of zero" — no pile at all, so nothing shows "×0" anywhere.
        $this->assertNull($this->inventory->findStackForUser($this->miraId, $this->itemId));
        $this->assertSame([], $this->inventory->findForUser($this->miraId));
    }

    public function testSellingTheSameSingleItemTwiceOnlyPaysOnce(): void
    {
        // THE IMPORTANT TEST IN THIS FILE. A double-tapped button on a slow phone
        // sends this exact pair of requests, and so does anybody deliberately
        // trying to mint currency. The second attempt must find nothing left.
        //
        // Honest about what this proves: the two calls here run one after the
        // other, not genuinely at the same instant, which PHPUnit cannot easily
        // arrange. What makes the simultaneous case safe is that the removal is a
        // single conditional UPDATE inside the database — two of those cannot both
        // match the same last item, whatever order they arrive in. This test pins
        // down the behaviour that protection produces.
        $this->inventory->addItem($this->miraId, $this->itemId);
        $before = $this->balanceOf($this->miraId);

        $first = $this->disposal->sell($this->miraId, $this->itemId);
        $second = $this->disposal->sell($this->miraId, $this->itemId);

        $this->assertTrue($first->isSuccessful());
        $this->assertFalse($second->isSuccessful(), 'The second sale of a single item must be refused.');
        $this->assertSame(
            $before + $this->itemSellValue,
            $this->balanceOf($this->miraId),
            'Mira was paid twice for one item — currency has been made out of nothing.'
        );
    }

    public function testRowanCannotSellSomethingMiraOwns(): void
    {
        $this->inventory->addItem($this->miraId, $this->itemId);
        $rowansBalance = $this->balanceOf($this->rowanId);

        // Rowan submits Mira's item id directly. There is no button for this; the
        // request simply arrives, which is the case the check has to survive.
        $result = $this->disposal->sell($this->rowanId, $this->itemId);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame($rowansBalance, $this->balanceOf($this->rowanId));
        // And Mira still has hers.
        $this->assertSame(1, $this->inventory->findStackForUser($this->miraId, $this->itemId)->quantity);
    }

    public function testSellingSomethingYouDoNotOwnIsRefused(): void
    {
        $result = $this->disposal->sell($this->miraId, $this->itemId);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $result->currencyEarned());
    }

    public function testAnItemNoShopWantsIsRefusedWithAnExplanation(): void
    {
        // Make the item worthless, so "cannot be sold" is exercised rather than
        // assumed. Every seeded item is sellable, which is why this is set up here.
        $this->connection->exec('UPDATE items SET sell_value = 0 WHERE id = ' . $this->itemId);
        $this->inventory->addItem($this->miraId, $this->itemId);
        $before = $this->balanceOf($this->miraId);

        $result = $this->disposal->sell($this->miraId, $this->itemId);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame($before, $this->balanceOf($this->miraId));
        // The item stays put — a refusal must not quietly consume it.
        $this->assertSame(1, $this->inventory->findStackForUser($this->miraId, $this->itemId)->quantity);
        // And the player is told why, rather than left with a button that did nothing.
        $this->assertStringContainsString('would buy', $result->message());

        $this->connection->exec(
            'UPDATE items SET sell_value = ' . $this->itemSellValue . ' WHERE id = ' . $this->itemId
        );
    }

    public function testDiscardingRemovesTheItemAndPaysNothing(): void
    {
        $this->inventory->addItem($this->miraId, $this->itemId);
        $before = $this->balanceOf($this->miraId);

        $result = $this->disposal->discard($this->miraId, $this->itemId);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(0, $result->currencyEarned());
        $this->assertSame($before, $this->balanceOf($this->miraId));
        $this->assertNull($this->inventory->findStackForUser($this->miraId, $this->itemId));
    }

    public function testRowanCannotThrowAwayMirasThings(): void
    {
        $this->inventory->addItem($this->miraId, $this->itemId);

        $result = $this->disposal->discard($this->rowanId, $this->itemId);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(1, $this->inventory->findStackForUser($this->miraId, $this->itemId)->quantity);
    }

    public function testTakingOneAwayTwiceFromASinglePileSucceedsExactlyOnce(): void
    {
        // The guarantee the whole file rests on, tested directly at the layer that
        // provides it, so a future change to the service cannot quietly lose it.
        $this->inventory->addItem($this->miraId, $this->itemId);

        $this->assertTrue($this->inventory->removeOne($this->miraId, $this->itemId));
        $this->assertFalse($this->inventory->removeOne($this->miraId, $this->itemId));
    }
}
