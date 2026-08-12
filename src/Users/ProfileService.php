<?php

declare(strict_types=1);

namespace Felkyo\Users;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Safety\TextGuard;

/**
 * The rules for changing your own profile.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS IS: the one place that decides whether a profile change is allowed,
 * and tidies it before it is saved.
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6), answered before this was
 * written, because a profile touches user data, permissions and free text:
 *
 * 1. WHO IS ALLOWED, AND HOW IS IT ENFORCED? Only the player themselves, and only
 *    on their own profile. There is no route by which one player edits another's,
 *    because every method here takes the acting player's id from the session and
 *    passes it into a WHERE clause. No method accepts "whose profile" as a value
 *    from the browser.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO?
 *    - Set their avatar to a path or a web address, so that every visitor to
 *      their page fetches a file of their choosing — which would hand them the IP
 *      address of everyone who looked, children included. Closed by AvatarSet:
 *      the value is a key checked against a list, not a filename.
 *    - Feature somebody else's creature, by sending its id. Closed twice: this
 *      class keeps only ids the player owns, and the repository's UPDATE names the
 *      owner as well.
 *    - Put a private creature on a public page by featuring it. Closed at the
 *      point of display — the profile only ever shows creatures marked public.
 *    - Write something harmful, or endless, in the about text. Checked by
 *      TextGuard, shared with account names and creature bios: length, hidden
 *      characters, contact details and blocked words. The link check is the one
 *      that matters most — a link is the one thing that leads somewhere none of
 *      this site's other protections reach.
 *    - Flood the page with featured creatures. Capped from config.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? tests/Integration/ProfileServiceTest proves
 *    each refusal: an avatar outside the set is rejected, another player's
 *    creature cannot be featured, a private creature never appears publicly, an
 *    over-long about text is refused, and the cap holds.
 */
final class ProfileService
{
    /**
     * @param array{max_about_length: int, max_featured_creatures: int} $limits
     */
    public function __construct(
        private ProfileRepository $profiles,
        private CreatureRepository $creatures,
        private AvatarSet $avatars,
        private TextGuard $textGuard,
        private array $limits,
    ) {
    }

    /**
     * Save a player's avatar and about text.
     */
    public function saveAppearance(int $userId, string $avatarKey, string $about): ProfileResult
    {
        // The avatar must be one we offer. Not "does it look like a safe value" —
        // is it in the list. A list cannot be argued with.
        if (!$this->avatars->has($avatarKey)) {
            return ProfileResult::rejected('That is not one of the avatars you can choose.');
        }

        // The about text goes through the same guard as account names and creature
        // bios (M1.4): length, hidden characters, contact details and blocked
        // words. Sharing one guard across all three is the point — three separate
        // sets of rules would mean three different sets of gaps.
        $guarded = $this->textGuard->checkLongText($about, $this->limits['max_about_length']);

        if (!$guarded->isAccepted()) {
            return ProfileResult::rejected($guarded->message());
        }

        // We save the guard's cleaned value, never the raw text.
        //
        // An empty box means "I have not written anything", which is NULL, not an
        // empty string. Keeping the difference lets the page say something warm in
        // the empty case instead of rendering nothing at all.
        $cleaned = $guarded->value();
        $this->profiles->saveAppearance($userId, $avatarKey, $cleaned === '' ? null : $cleaned);

        return ProfileResult::saved('Your page has been saved.');
    }

    /**
     * Choose which creatures a player features, and in what order.
     *
     * @param array<int, int> $creatureIds Whatever the form sent, in order.
     */
    public function saveFeatured(int $userId, array $creatureIds): ProfileResult
    {
        // Take only ids this player actually owns. Anything else is dropped
        // silently rather than refused: a player who tampered with the form gets
        // no confirmation that the id they guessed belongs to somebody, and a
        // player whose creature was rehomed mid-edit is not shouted at for it.
        $ownedIds = array_map(
            static fn ($creature): int => $creature->id,
            $this->creatures->findByOwner($userId)
        );

        $chosen = array_values(array_unique(
            array_filter($creatureIds, static fn (int $id): bool => in_array($id, $ownedIds, true))
        ));

        if (count($chosen) > $this->limits['max_featured_creatures']) {
            return ProfileResult::rejected(
                'You can feature up to ' . $this->limits['max_featured_creatures'] . ' creatures at once.'
            );
        }

        $this->profiles->replaceFeatured($userId, $chosen);

        return ProfileResult::saved(
            $chosen === []
                ? 'Your page now shows your newest creatures.'
                : 'Your featured creatures have been saved.'
        );
    }
}
