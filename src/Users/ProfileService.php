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
     * Save a player's avatar, about text, and whether they can be found by
     * search.
     *
     * The findable setting takes no validating — it is a yes or a no, and both
     * are allowed. It travels with the rest so that one "Save" button saves the
     * whole form, which is what a player expects from one screen.
     */
    public function saveAppearance(int $userId, string $avatarKey, string $about, bool $isFindable): ProfileResult
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
        $this->profiles->saveAppearance($userId, $avatarKey, $cleaned === '' ? null : $cleaned, $isFindable);

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

    /**
     * Add or remove ONE creature from a player's favourites, from wherever they
     * happen to be looking at it.
     *
     * WHY THIS DELEGATES RATHER THAN WRITING ITS OWN QUERY. Choosing favourites
     * already has rules: only creatures you own count, duplicates collapse, and
     * there is a cap. Those rules live in saveFeatured() above, and a second way
     * of setting favourites that wrote to the table directly would be a second
     * place for them to be forgotten — which is exactly how the community page
     * shipped without any of the search guards. So this works out the new LIST and
     * hands it to the method that already knows the rules.
     *
     * WHY IT IS A TOGGLE AND NOT SEPARATE ADD/REMOVE ROUTES. The star on a
     * creature's page shows its current state, so the only thing a player can mean
     * by pressing it is "the other one". Two routes would mean the browser telling
     * us which — and a stale page would then be able to remove a favourite the
     * player had just added. The server reading the current state is the only
     * version of this that cannot get out of step.
     *
     * @param string $creatureName Only for the message; the id is what is trusted.
     */
    public function toggleFavourite(int $userId, int $creatureId, string $creatureName): ProfileResult
    {
        $current = $this->creatures->findFeaturedIds($userId);
        $wasFavourite = in_array($creatureId, $current, true);

        $next = $wasFavourite
            ? array_values(array_filter($current, static fn (int $id): bool => $id !== $creatureId))
            : [...$current, $creatureId];

        $result = $this->saveFeatured($userId, $next);

        // A refusal is passed straight through: the only one possible here is the
        // cap, and its wording ("you can feature up to six at once") is already
        // exactly what somebody needs to read.
        if (!$result->isSuccessful()) {
            return $result;
        }

        return ProfileResult::saved(
            $wasFavourite
                ? $creatureName . ' is no longer one of your favourites.'
                : $creatureName . ' is now one of your favourites.'
        );
    }
}
