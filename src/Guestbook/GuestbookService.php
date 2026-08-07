<?php

declare(strict_types=1);

namespace Felkyo\Guestbook;

use Felkyo\Creatures\Creature;

/**
 * The rules for signing a creature's guestbook.
 *
 * @package Felkyo\Guestbook
 *
 * WHAT THIS IS: the one place that decides what happens when someone signs. The
 * three rules it enforces, in the order it checks them:
 *
 *   1. The chosen message must be one we actually offer. Anything else is refused —
 *      this is what stops a made-up key being submitted by editing the form.
 *   2. One entry per person per creature. A second signing does not add a row; it
 *      changes the row that is already there.
 *   3. An entry may be changed once a day. Choosing the SAME message again is a
 *      no-op that deliberately does not use up that daily change.
 *
 * WHO is allowed to sign (logged in, and able to see the creature) is checked by
 * the controller before calling this. This class is about "is this signing valid,
 * and store it" — keeping the two kinds of rule in separate layers.
 */
final class GuestbookService
{
    public function __construct(
        private GuestbookRepository $entries,
        private GuestbookMessages $messages,
        private int $editCooldownSeconds,
    ) {
    }

    /**
     * Sign (or re-sign) a creature's guestbook on behalf of a user.
     *
     * Returns a GuestbookResult saying what happened, with a message to show the
     * visitor either way.
     */
    public function sign(int $authorUserId, Creature $creature, string $messageKey): GuestbookResult
    {
        // Rule 1: only a message from the list may be chosen.
        if (!$this->messages->has($messageKey)) {
            return GuestbookResult::rejected('Please choose one of the messages.');
        }

        $existing = $this->entries->findByCreatureAndAuthor($creature->id, $authorUserId);

        // Rule 2: nobody has signed twice — a first signing simply adds the entry.
        if ($existing === null) {
            $this->entries->add($creature->id, $authorUserId, $messageKey);

            return GuestbookResult::signed('You signed ' . $creature->name . '\'s guestbook.');
        }

        // Choosing the message that is already there changes nothing, so we say so
        // and leave the daily allowance untouched. Spending someone's one change a
        // day on a no-op would be an unpleasant surprise.
        if ($existing->messageKey === $messageKey) {
            return GuestbookResult::unchanged('That is already your message in this guestbook.');
        }

        // Rule 3: a change is allowed only once a day.
        if ($this->entries->wasChangedRecently($existing->id, $this->editCooldownSeconds)) {
            return GuestbookResult::rejected(
                'You can change your guestbook message once a day — try again tomorrow.'
            );
        }

        $this->entries->changeMessage($existing->id, $messageKey);

        return GuestbookResult::changed('Your guestbook message was changed.');
    }
}
