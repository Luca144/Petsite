<?php

declare(strict_types=1);

namespace Felkyo\Social;

use Felkyo\Pound\PoundResult;
use PDO;

/**
 * Setting a bottle adrift and finding one on the shore (M6.5).
 *
 * @package Felkyo\Social
 *
 * One bottle sent and one found per person per day, each a daily-claim row
 * the database won't let you have twice. The message is chosen from a fixed
 * catalogue (config gameplay.bottles.messages) by its KEY — nothing typed.
 *
 * A note that belongs beside the catalogue, repeated here so it survives:
 * even without typing, WHICH message someone picks carries meaning. The set
 * stays warm and cheerful and never confessional — nothing about loneliness,
 * age or circumstances — or someone could open bottles hunting for a
 * vulnerable sender, which is exactly what randomness was meant to stop.
 */
final class BottleService
{
    private const SEND = 'bottle_send';
    private const FIND = 'bottle_find';

    /** @param array<string, string> $messages key => words */
    public function __construct(
        private PDO $connection,
        private BottleRepository $bottles,
        private array $messages,
    ) {
    }

    /** @return array<string, string> the catalogue, for the shore page. */
    public function messages(): array
    {
        return $this->messages;
    }

    public function wordsFor(string $key): string
    {
        return $this->messages[$key] ?? 'The ink has run, but somebody meant well.';
    }

    public function canSendToday(int $userId): bool
    {
        return !$this->bottles->hasClaimedToday($userId, self::SEND);
    }

    public function canFindToday(int $userId): bool
    {
        return !$this->bottles->hasClaimedToday($userId, self::FIND);
    }

    public function send(int $userId, string $messageKey): PoundResult
    {
        if (!array_key_exists($messageKey, $this->messages)) {
            return PoundResult::refused('Pick one of the messages first.');
        }
        if (!$this->bottles->claimToday($userId, self::SEND)) {
            return PoundResult::refused('You\'ve already set a bottle adrift today — the sea needs time. Try again tomorrow.');
        }
        $this->bottles->setAdrift($userId, $messageKey);

        return PoundResult::ok('Your bottle bobs away on the tide. Someone, somewhere, will find it.');
    }

    public function find(int $userId): PoundResult
    {
        $this->connection->beginTransaction();
        try {
            if (!$this->bottles->claimToday($userId, self::FIND)) {
                $this->connection->rollBack();

                return PoundResult::refused('You\'ve already walked the shore today. Come back tomorrow — the tide turns at midnight.');
            }
            $bottleId = $this->bottles->washUpFor($userId);
            if ($bottleId === null) {
                // Nothing washed up: give the day's walk back, so trying
                // again later costs nothing (never punish absence or luck).
                $this->bottles->releaseToday($userId, self::FIND);
                $this->connection->commit();

                return PoundResult::refused('Just shells and seaweed this time — no bottles are drifting right now. Try again later!');
            }
            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }

        return PoundResult::ok('A bottle! You pull out the little rolled note…', $bottleId);
    }
}
