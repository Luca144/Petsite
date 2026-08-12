<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * The kinds of thing that can be reported, and what happens when they are.
 *
 * @package Felkyo\Safety
 *
 * WHY AN ENUM AND NOT A STRING. A report says what kind of thing it is about, and
 * that value arrives from a form. If it were a plain string, a request could
 * invent a kind — and every later piece of code would have to guess what to do
 * with it. An enum means only these five values can ever exist, and adding a
 * sixth (cards and bottles, in M6) is a deliberate edit here rather than a value
 * somebody typed.
 *
 * THE HIDING RULE LIVES HERE, beside the kinds it applies to, so that adding a
 * new kind forces whoever adds it to answer the question "does this hide?" rather
 * than leaving it to be discovered later.
 */
enum ReportSubject: string
{
    case Username = 'username';
    case CreatureName = 'creature_name';
    case CreatureBio = 'creature_bio';
    case ProfileAbout = 'profile_about';
    case GuestbookEntry = 'guestbook_entry';

    /**
     * Does reporting this hide it until a human has looked?
     *
     * TRUE for the long free texts. With two part-time moderators a report may
     * wait hours, and the worst case of hiding is somebody's paragraph being
     * invisible for an evening. The worst case of not hiding is harmful text
     * sitting in public all night. Those are not comparable.
     *
     * FALSE for names. Hiding a name would break every page it appears on, and
     * worse, it would let anybody remove another player from the site simply by
     * reporting them. Names are flagged for review instead.
     *
     * FALSE for guestbook entries only because they are already chosen from a
     * fixed list of messages we wrote — there is nothing in one to hide. What is
     * reportable about a guestbook entry is the PATTERN of who keeps signing.
     */
    public function hidesUntilReviewed(): bool
    {
        return match ($this) {
            self::CreatureBio, self::ProfileAbout => true,
            self::Username, self::CreatureName, self::GuestbookEntry => false,
        };
    }

    /**
     * What a player sees this called, for the confirmation message.
     */
    public function label(): string
    {
        return match ($this) {
            self::Username => 'this player’s name',
            self::CreatureName => 'this creature’s name',
            self::CreatureBio => 'this creature’s description',
            self::ProfileAbout => 'this player’s about text',
            self::GuestbookEntry => 'this guestbook entry',
        };
    }

    /**
     * Turn a value from a form into a kind, or null if it is not one of ours.
     *
     * Deliberately returns null rather than throwing: a bad value here means
     * somebody sent something odd, which is a request to refuse quietly, not an
     * error worth a stack trace.
     */
    public static function fromFormValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
