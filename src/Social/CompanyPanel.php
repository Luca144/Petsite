<?php

declare(strict_types=1);

namespace Felkyo\Social;

/**
 * What a player's page needs from M6: whether the viewer has bookmarked or
 * blocked this player, and the mantelpiece of cards they've received.
 *
 * @package Felkyo\Social
 *
 * Gathered here so ProfileController asks one question instead of three.
 * Costs at most three small indexed queries per profile view.
 */
final class CompanyPanel
{
    private const MANTELPIECE_SIZE = 12;

    public function __construct(
        private CompanyRepository $company,
        private PostcardRepository $postcards,
    ) {
    }

    /**
     * @return array{viewerIsLoggedIn: bool, isFriend: bool, isBlocked: bool, cards: array}
     */
    public function forProfile(int $profileUserId, ?int $viewerId): array
    {
        $looking = $viewerId !== null && $viewerId !== $profileUserId;

        return [
            'viewerIsLoggedIn' => $viewerId !== null,
            'isFriend' => $looking && $this->company->isFriend($viewerId, $profileUserId),
            'isBlocked' => $looking && $this->company->hasBlocked($viewerId, $profileUserId),
            'cards' => $this->postcards->mantelpieceOf($profileUserId, self::MANTELPIECE_SIZE),
        ];
    }
}
