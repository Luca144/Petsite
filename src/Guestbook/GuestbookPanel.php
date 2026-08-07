<?php

declare(strict_types=1);

namespace Felkyo\Guestbook;

use Felkyo\Creatures\Creature;

/**
 * Gathers everything the creature page needs to show a guestbook.
 *
 * @package Felkyo\Guestbook
 *
 * WHY THIS EXISTS: displaying a guestbook needs three separate things — the
 * signatures themselves, the list of messages a visitor may choose from, and which
 * message THIS visitor already picked (so their choice shows as selected). Collecting
 * them in one small helper keeps CreatureController thin: it asks for "the guestbook
 * panel" and passes it to the template.
 *
 * This mirrors CreatureProfileBuilder, which does the same job for a creature's own
 * details — same pattern, different subject.
 */
final class GuestbookPanel
{
    public function __construct(
        private GuestbookRepository $entries,
        private GuestbookMessages $messages,
        private int $entriesShown,
    ) {
    }

    /**
     * Build the guestbook display data for one creature.
     *
     * @param int|null $viewerId The logged-in visitor's id, or null for a guest.
     * @return array{entries: GuestbookEntry[], messages: GuestbookMessages, chosenKey: string|null}
     */
    public function forCreature(Creature $creature, ?int $viewerId): array
    {
        // A guest has not signed anything, so we do not need to look.
        $ownEntry = $viewerId === null
            ? null
            : $this->entries->findByCreatureAndAuthor($creature->id, $viewerId);

        return [
            'entries' => $this->entries->listForCreature($creature->id, $this->entriesShown),
            // The catalogue travels to the template so it can turn each stored
            // message key into the text players actually read.
            'messages' => $this->messages,
            // Which message this visitor chose, so their radio starts selected.
            'chosenKey' => $ownEntry?->messageKey,
        ];
    }
}
