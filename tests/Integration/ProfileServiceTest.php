<?php

declare(strict_types=1);

namespace Felkyo\Tests\Integration;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Tests\DatabaseTestCase;
use Felkyo\Tests\Support\Guards;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\ProfileService;
use Felkyo\Users\UserRepository;

/**
 * Tests the rules for changing your own profile.
 *
 * @package Felkyo\Tests\Integration
 *
 * Most of these are refusals, because CLAUDE.md asks for proof that the bad case
 * is refused rather than only that the good case works. The scenario throughout:
 * Mira has a profile and creatures, Rowan has his own, and each tries to reach
 * past their own edge.
 */
final class ProfileServiceTest extends DatabaseTestCase
{
    private ProfileService $service;
    private ProfileRepository $profiles;
    private CreatureRepository $creatures;
    private int $miraId;
    private int $rowanId;
    private int $rowansCreatureId;
    private array $mirasCreatureIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTables('inventory', 'pettings', 'creatures', 'users');

        $users = new UserRepository($this->connection);
        $this->profiles = new ProfileRepository($this->connection);
        $this->creatures = new CreatureRepository($this->connection);

        $this->service = new ProfileService(
            $this->profiles,
            $this->creatures,
            new AvatarSet([
                'default' => ['name' => 'The wandering visitor', 'file' => 'default.png'],
                'second' => ['name' => 'A second face', 'file' => 'second.png'],
            ]),
            Guards::textGuard(['spam', 'scam']),
            ['max_about_length' => 300, 'max_featured_creatures' => 3]
        );

        $this->miraId = $users->create('mira', 'mira@example.com', 'hash')->id;
        $this->rowanId = $users->create('rowan', 'rowan@example.com', 'hash')->id;

        $speciesId = (new SpeciesRepository($this->connection))->findStarters()[0]->id;
        foreach (['Biscuit', 'Pip', 'Moss', 'Fern'] as $name) {
            $this->mirasCreatureIds[] = $this->creatures->create($this->miraId, $speciesId, $name)->id;
        }
        $this->rowansCreatureId = $this->creatures->create($this->rowanId, $speciesId, 'Thistle')->id;
    }

    // ---- Avatars: the allow-list ----

    public function testAnAvatarFromTheSetIsAccepted(): void
    {
        $result = $this->service->saveAppearance($this->miraId, 'second', 'Hello!');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('second', $this->profiles->findById($this->miraId)->avatarKey);
    }

    public function testAnAvatarThatIsNotInTheSetIsRefused(): void
    {
        $result = $this->service->saveAppearance($this->miraId, 'invented-key', 'Hello!');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('default', $this->profiles->findById($this->miraId)->avatarKey);
    }

    public function testAnAvatarThatIsAFilePathIsRefused(): void
    {
        // The reason the column holds a key and not a filename. If this were ever
        // accepted, the value would end up inside an <img src="...">.
        foreach (['../../../.env', '/etc/passwd', 'https://example.com/tracker.png', 'default.png'] as $attempt) {
            $result = $this->service->saveAppearance($this->miraId, $attempt, '');

            $this->assertFalse(
                $result->isSuccessful(),
                "\"{$attempt}\" was accepted as an avatar. Only keys from the set may be."
            );
        }

        $this->assertSame('default', $this->profiles->findById($this->miraId)->avatarKey);
    }

    // ---- The about text ----

    public function testAnAboutTextIsSavedAndTrimmed(): void
    {
        $this->service->saveAppearance($this->miraId, 'default', '   Hello from Mira.   ');

        $this->assertSame('Hello from Mira.', $this->profiles->findById($this->miraId)->about);
    }

    public function testAnEmptyAboutTextIsStoredAsNothingRatherThanAnEmptyString(): void
    {
        // "Never written" and "written, then cleared" should look the same to the
        // page, so it can offer a warm empty state either way.
        $this->service->saveAppearance($this->miraId, 'default', '    ');

        $this->assertNull($this->profiles->findById($this->miraId)->about);
    }

    public function testAnOverLongAboutTextIsRefusedAndNothingIsSaved(): void
    {
        $result = $this->service->saveAppearance($this->miraId, 'second', str_repeat('a', 301));

        $this->assertFalse($result->isSuccessful());
        $profile = $this->profiles->findById($this->miraId);
        $this->assertNull($profile->about);
        // The avatar was valid, but the save is refused as a whole — half-applying
        // a form would leave somebody unsure what actually happened.
        $this->assertSame('default', $profile->avatarKey);
    }

    public function testAnAboutTextWithABlockedWordIsRefused(): void
    {
        $result = $this->service->saveAppearance($this->miraId, 'default', 'buy my spam here');

        $this->assertFalse($result->isSuccessful());
        $this->assertNull($this->profiles->findById($this->miraId)->about);
    }

    public function testTextThatLooksLikeCodeIsStoredAsPlainWords(): void
    {
        // Nothing here is executed or interpreted; it is just characters. The
        // escaping on output is what keeps it inert, and this pins down that the
        // storage layer does not mangle it either.
        $awkward = "<script>alert('hi')</script> ' OR 1=1 --";

        $this->service->saveAppearance($this->miraId, 'default', $awkward);

        $this->assertSame($awkward, $this->profiles->findById($this->miraId)->about);
    }

    // ---- Featured creatures ----

    public function testAPlayerCanFeatureTheirOwnCreaturesInOrder(): void
    {
        $chosen = [$this->mirasCreatureIds[2], $this->mirasCreatureIds[0]];

        $result = $this->service->saveFeatured($this->miraId, $chosen);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame($chosen, $this->creatures->findFeaturedIds($this->miraId));
    }

    public function testRowansCreatureCannotBeFeaturedOnMirasPage(): void
    {
        $this->service->saveFeatured($this->miraId, [$this->rowansCreatureId]);

        $this->assertSame([], $this->creatures->findFeaturedIds($this->miraId));
        // And Rowan's own page is untouched by Mira's attempt.
        $this->assertSame([], $this->creatures->findFeaturedIds($this->rowanId));
    }

    public function testSomebodyElsesCreatureIsDroppedButYourOwnStillSave(): void
    {
        $this->service->saveFeatured(
            $this->miraId,
            [$this->mirasCreatureIds[1], $this->rowansCreatureId, $this->mirasCreatureIds[0]]
        );

        // The stranger's id is quietly ignored; Mira's two are kept, in order.
        $this->assertSame(
            [$this->mirasCreatureIds[1], $this->mirasCreatureIds[0]],
            $this->creatures->findFeaturedIds($this->miraId)
        );
    }

    public function testFeaturingMoreThanTheCapIsRefused(): void
    {
        $result = $this->service->saveFeatured($this->miraId, $this->mirasCreatureIds);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame([], $this->creatures->findFeaturedIds($this->miraId));
    }

    public function testTheSameCreatureTwiceCountsOnce(): void
    {
        $id = $this->mirasCreatureIds[0];

        $result = $this->service->saveFeatured($this->miraId, [$id, $id, $id, $id]);

        $this->assertTrue($result->isSuccessful(), 'Repeats should collapse rather than trip the cap.');
        $this->assertSame([$id], $this->creatures->findFeaturedIds($this->miraId));
    }

    public function testChoosingNoneClearsWhatWasFeaturedBefore(): void
    {
        $this->service->saveFeatured($this->miraId, [$this->mirasCreatureIds[0]]);

        $this->service->saveFeatured($this->miraId, []);

        $this->assertSame([], $this->creatures->findFeaturedIds($this->miraId));
    }

    // ---- What the public page actually shows ----

    public function testAPrivateCreatureNeverAppearsOnTheProfileEvenWhenFeatured(): void
    {
        // The important one. A player features a creature, then makes it private.
        // Nobody has to remember to un-feature it — the page filters at the point
        // of display, so the creature simply stops appearing.
        $privateId = $this->mirasCreatureIds[0];
        $this->service->saveFeatured($this->miraId, [$privateId]);
        $this->connection->exec('UPDATE creatures SET is_public = 0 WHERE id = ' . $privateId);

        $shown = array_map(
            static fn ($creature): int => $creature->id,
            $this->creatures->findForProfile($this->miraId, 12)
        );

        $this->assertNotContains($privateId, $shown);
        // It is still ticked in the owner's own settings, so their choice is not
        // silently thrown away.
        $this->assertSame([$privateId], $this->creatures->findFeaturedIds($this->miraId));
    }

    public function testFeaturedCreaturesComeFirstThenTheRest(): void
    {
        $featured = $this->mirasCreatureIds[3];
        $this->service->saveFeatured($this->miraId, [$featured]);

        $shown = $this->creatures->findForProfile($this->miraId, 12);

        $this->assertSame($featured, $shown[0]->id, 'A featured creature should lead the page.');
        $this->assertCount(4, $shown, 'The rest should still follow it.');
    }

    public function testAProfileNeverCarriesTheEmailOrPasswordHash(): void
    {
        // The Profile type simply has no such fields, which is the protection —
        // but a test says so out loud, so nobody adds them later thinking it is
        // harmless.
        $profile = $this->profiles->findByUsername('mira');

        $this->assertObjectNotHasProperty('email', $profile);
        $this->assertObjectNotHasProperty('passwordHash', $profile);
    }
}
