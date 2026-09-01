<?php

declare(strict_types=1);

namespace Felkyo\Moderation;

/**
 * One official notice to a player — the one-way channel (M2.7).
 *
 * @package Felkyo\Moderation
 *
 * A notice is how the site tells a player something happened to their
 * account: a card at the top of every page until they press "I've read
 * this". Players cannot reply — appeals go to the Felkyo email, which the
 * card names (the address itself comes from config and is appended by the
 * card partial, not stored here).
 *
 * THE WORDS LIVE HERE, KEYED BY KIND, and nowhere else. `kind` is what
 * the database stores; a moderator picks an ACTION, never types a
 * sentence — so what a child reads was written once, calmly, in this
 * file, and is the same for everyone. An unknown kind (say, a row from a
 * newer version of the site) renders the generic wording rather than
 * nothing — a notice must never be a blank card.
 *
 * `detail` is machine-formatted by the moderation service (today: only a
 * date for timed states). It is never typed input.
 */
final class ModerationNotice
{
    public function __construct(
        public readonly int $id,
        public readonly string $kind,
        public readonly ?string $detail,
        public readonly string $createdAt,
        public readonly ?string $readAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['kind'],
            $row['detail'] !== null ? (string) $row['detail'] : null,
            (string) $row['created_at'],
            $row['read_at'] !== null ? (string) $row['read_at'] : null,
        );
    }

    /**
     * The card's heading — short, plain, never accusatory.
     */
    public function heading(): string
    {
        return match ($this->kind) {
            'warned' => 'A note from the Felkyo team',
            'bio_removed' => 'A creature description was removed',
            'about_removed' => 'Your about text was removed',
            'player_renamed' => 'Your name was changed',
            'creature_renamed' => 'A creature\'s name was changed',
            'muted' => 'Writing is paused for a while',
            'suspended' => 'Your account was paused for a while',
            default => 'A note from the Felkyo team',
        };
    }

    /**
     * The card's body. Written for the player who reads it — often a
     * child, often an upset one: what happened, plainly, without jargon
     * and without scolding. The appeal line (with the email address) is
     * added by the card itself, once, under whichever body this is.
     */
    public function body(): string
    {
        return match ($this->kind) {
            'warned' =>
                'Something on your page was reported, and a member of the team '
                . 'looked at it. Nothing was changed this time — but please have '
                . 'a look at what you\'ve written, and keep Felkyo kind.',
            'bio_removed' =>
                'One of your creatures had a description that broke our rules, '
                . 'so the team removed it. You can write a new one any time.',
            'about_removed' =>
                'Your page\'s about text broke our rules, so the team removed '
                . 'it. You can write a new one any time.',
            'player_renamed' =>
                'Your name broke our rules, so the team gave you a plain one '
                . 'for now. You can pick a new name from your page settings.',
            'creature_renamed' =>
                'One of your creatures had a name that broke our rules, so the '
                . 'team gave them a plain one for now. You can rename them any '
                . 'time.',
            'muted' =>
                'Because of something you wrote, you can\'t write new text on '
                . 'Felkyo' . $this->untilWords() . ' — names, descriptions and '
                . 'guestbooks are paused. Everything else still works: your '
                . 'creatures are fine, and they\'re glad you\'re here.',
            'suspended' =>
                'Your account was paused' . $this->untilWords() . ' because of '
                . 'something that happened here. Your creatures are safe and '
                . 'will be waiting for you.',
            default =>
                'A member of the team looked at something on your account. '
                . 'If anything looks different, this is why.',
        };
    }

    /** " until 2026-09-07", or nothing when there is no detail. */
    private function untilWords(): string
    {
        return $this->detail !== null ? ' until ' . $this->detail : '';
    }
}
