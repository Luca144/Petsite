<?php

declare(strict_types=1);

namespace Felkyo\Social;

use Felkyo\Pound\PoundResult;
use Felkyo\Users\UserRepository;
use PDO;

/**
 * Buying and sending a postcard (M6.4).
 *
 * @package Felkyo\Social
 *
 * THE RULES, and where each is enforced:
 *  - Anyone may be sent a card, friend or not — that's what stops the
 *    feature punishing people who know nobody.
 *  - Never to yourself; never to someone who has blocked you (they simply
 *    won't receive it — we say so plainly, without saying why).
 *  - Words are the card's own, written by the project. Players type nothing.
 *  - A daily cap per sender, so nobody can be flooded (config).
 *  - Paying and sending happen in ONE transaction, the payment first as a
 *    conditional UPDATE (house pattern 3): no gems, no card; a failure after
 *    paying rolls the payment back.
 */
final class PostOfficeService
{
    public function __construct(
        private PDO $connection,
        private PostcardRepository $postcards,
        private CompanyRepository $company,
        private UserRepository $users,
        private int $dailyCap,
        private string $currencyName,
    ) {
    }

    public function send(int $senderId, int $designId, string $recipientName): PoundResult
    {
        $design = $this->postcards->designOnSale($designId);
        if ($design === null) {
            return PoundResult::refused('Pick a card first.');
        }

        $recipient = $this->users->findByUsername(trim($recipientName));
        if ($recipient === null) {
            return PoundResult::refused('There\'s nobody called that in Felkyo — check the spelling?');
        }
        if ($recipient->id === $senderId) {
            return PoundResult::refused('Cards are for sending to someone else — pick another name.');
        }
        if ($this->company->hasBlocked($recipient->id, $senderId)) {
            return PoundResult::refused(sprintf('%s isn\'t receiving cards from you.', $recipient->username));
        }
        if ($this->postcards->countSentSince($senderId, 86400) >= $this->dailyCap) {
            return PoundResult::refused(sprintf('That\'s %d cards today — the post office opens again tomorrow.', $this->dailyCap));
        }

        $this->connection->beginTransaction();
        try {
            if (!$this->users->deductCurrency($senderId, $design['gem_price'])) {
                $this->connection->rollBack();

                return PoundResult::refused(sprintf(
                    'That card costs %d %s — pet a few creatures and come back.',
                    $design['gem_price'], $this->currencyName
                ));
            }
            $this->postcards->send($senderId, $recipient->id, $design['id']);
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        return PoundResult::ok(sprintf('Your "%s" card is on its way to %s — it will be on their mantelpiece.', $design['name'], $recipient->username));
    }
}
