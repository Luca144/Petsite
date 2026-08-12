<?php

declare(strict_types=1);

namespace Felkyo\Users;

use Felkyo\Creatures\ContentFilter;
use Felkyo\Creatures\CreatureRepository;

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
 *    - Write something harmful, or endless, in the about text. Bounded here by a
 *      length cap and the word filter. Honestly: this is the WEAKEST of the four
 *      today, and M1.4 is the increment that fixes it properly — links, lookalike
 *      characters, impersonation and reporting. Until then the about text carries
 *      exactly the protection the creature bio already has, and no more.
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
        private ContentFilter $filter,
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

        $about = trim($about);

        if (mb_strlen($about) > $this->limits['max_about_length']) {
            return ProfileResult::rejected(
                'Please keep your about text to ' . $this->limits['max_about_length'] . ' characters or fewer.'
            );
        }

        if ($this->filter->containsBlockedWord($about)) {
            return ProfileResult::rejected('Please keep it friendly — some words in there are not allowed.');
        }

        // An empty box means "I have not written anything", which is NULL, not an
        // empty string. Keeping the difference lets the page say something warm in
        // the empty case instead of rendering nothing at all.
        $this->profiles->saveAppearance($userId, $avatarKey, $about === '' ? null : $about);

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
