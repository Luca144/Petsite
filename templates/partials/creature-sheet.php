<?php
/**
 * The creature sheet: portrait, name, mood, stats, and the doings.
 *
 * @package Felkyo\Templates
 *
 * The heart of the creature page, extracted (M2.3) so TWO screens can render
 * the SAME markup: the live page (pages/creature.php) and the species
 * editor's preview (pages/admin-creature-preview.php). The preview promise —
 * "rendered on the real page layout" — is kept by there being exactly one
 * layout to render; if this sheet changes, the preview changes with it,
 * because they are the same file.
 *
 * Variables:
 *   $creature (Creature), $species (?Species), $stage (string), $level (int),
 *   $timesPetted (int), $mood (Mood), $owner (?User),
 *   $canPet (bool), $isOwner (bool), $treats (array, owner only),
 *   $justPetted (bool, from the layout's shared data).
 */

$speciesName = $species?->name ?? 'Unknown species';

// THE sprite address comes from the species itself — art-store picture if
// one is linked, the pre-panel convention path if not (Species::spriteUrlFor,
// the one resolver that replaced six copied-out paths).
$imagePath = $species?->spriteUrlFor($stage) ?? '';

$createdLabel = $creature->createdAt !== null
    ? date('j M Y', (int) strtotime($creature->createdAt))
    : 'unknown';
?>

<article class="creature">
    <?php /* After a pet, --celebrate does three things at once: the creature gives a
             happy wriggle, three hearts drift up at slightly different times, and the
             two numbers underneath light up. One small heart was too quiet to notice —
             petting is the thing players do most on this site and it should feel like
             something happened. All of it is CSS and all of it is skipped entirely
             under prefers-reduced-motion. */ ?>
    <div class="creature__portrait<?= !empty($justPetted) ? ' creature__portrait--celebrate' : '' ?>">
        <?php
        // The largest WHOLE multiple of the sprite's native pixels that fits the
        // frame — a fractional scale smears pixel art (see PixelArt). 216 leaves
        // the sprite a little air inside the 240 frame.
        [$artWidth, $artHeight] = \Felkyo\Design\PixelArt::displaySize($imagePath, 216);
        ?>
        <span class="creature__frame">
            <img class="creature__img pixelated"
                 src="<?= $this->e($imagePath) ?>"
                 alt="<?= $this->e($creature->name . ', a ' . $speciesName . ' (' . $stage . ')') ?>"
                 width="<?= $this->e((string) $artWidth) ?>" height="<?= $this->e((string) $artHeight) ?>">
        </span>

        <?php if (!empty($justPetted)): ?>
            <?php /* Purely decorative, so hidden from screen readers — the flash
                     message above already says what happened, in words. */ ?>
            <span class="creature__hearts" aria-hidden="true">
                <span>&hearts;</span><span>&hearts;</span><span>&hearts;</span>
            </span>
        <?php endif; ?>
    </div>

    <div class="creature__info">
        <h1><?= $this->e($creature->name) ?></h1>
        <p class="creature__meta">
            a <b><?= $this->e($speciesName) ?></b>
            <?php /* The owner's name links to their page, so a creature you liked
                     is a way of finding the person who looks after it. */ ?>
            <?php if ($creature->isAtPound()): ?>
                &middot; waiting for a home at <a href="/pound">the pound</a>
            <?php else: ?>
            &middot; cared for by
            <?php if ($owner !== null): ?>
                <a href="/player/<?= $this->e(rawurlencode($owner->username)) ?>"><?= $this->e($owner->username) ?></a>
            <?php else: ?>
                someone
            <?php endif; ?>
            <?php endif; ?>
            &middot; here since <?= $this->e($createdLabel) ?>
        </p>
        <?php
        // The stage's own words if the artist wrote some (M2.3), else the
        // species-wide flavour text — the stage blurb is the more specific
        // sentence, so it wins where both exist.
        $flavour = $species?->blurbFor($stage) ?? $species?->flavourText ?? null;
        ?>
        <?php if (!empty($flavour)): ?>
            <p class="creature__flavour"><?= $this->e($flavour) ?></p>
        <?php endif; ?>

        <?php /* How the creature is FEELING, in words, above the numbers. This is
                 the line a player should read first — "Biscuit is very happy" is
                 something you can feel about, where "87" is a readout. The bars
                 below carry the same two facts a second way, which is the rule
                 that nothing important may ride on one signal alone. */ ?>
        <p class="creature__mood" role="status">
            <?= $this->e($mood->sentence($creature->name)) ?>
        </p>

        <?= $this->insert('partials/mood-bars', ['mood' => $mood, 'justPetted' => $justPetted ?? false]) ?>

        <!-- Current state. Level and stage rise as the creature is petted. -->
        <div class="card card--dark creature-stats">
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e((string) $level) ?></span>
                <span class="creature-stat__label">level</span>
            </div>
            <div class="creature-stat">
                <?php /* --word: the stage is a word between two numbers, and at
                         the numbers' display size "juvenile" is wider than a
                         phone-width cell (see creature.css). */ ?>
                <span class="creature-stat__value creature-stat__value--word"><?= $this->e($stage) ?></span>
                <span class="creature-stat__label">stage</span>
            </div>
            <div class="creature-stat">
                <span class="creature-stat__value"><?= $this->e((string) $timesPetted) ?></span>
                <span class="creature-stat__label">times petted</span>
            </div>
        </div>

        <?php if ($canPet): ?>
            <div class="creature__doings">
                <form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/pet" class="creature__pet">
                    <?= $this->csrf_field() ?>
                    <button class="btn btn--primary" type="submit">
                        <span aria-hidden="true">&hearts;</span> Pet <?= $this->e($creature->name) ?>
                    </button>
                </form>

                <?php if (!empty($isOwner)): ?>
                    <?php /* A game, for the owner. A link, because starting one
                             changes nothing — the round opens on the page you land
                             on, and the answer stays on the server. */ ?>
                    <a class="btn btn--secondary"
                       href="/creature/<?= $this->e((string) $creature->id) ?>/play">
                        <span aria-hidden="true">&#9670;</span> Play a game
                    </a>
                <?php endif; ?>
            </div>
        <?php elseif (empty($previewMode)): ?>
            <p class="auth-alt"><a href="/login">Log in</a> to pet <?= $this->e($creature->name) ?>.</p>
        <?php endif; ?>

        <?php if (!empty($isOwner)): ?>
            <?php /* The two things only an owner can do here.

                     THE STAR sits right beside the creature it is about.
                     Favourites used to be chosen only from the profile settings
                     page, out of a list of names — so saying "this one" meant
                     leaving the thing you were pointing at. It matters more now:
                     the favourite is the creature on the keepsake card, which
                     appears on every page.

                     FEEDING is in its own partial because it is the longest thing
                     on this page and it is also used nowhere else — extracting it
                     kept this template under its 200-line limit without pretending
                     the two are related. */ ?>
            <?= $this->insert('partials/favourite-star', [
                'creature' => $creature,
                'from' => 'creature',
            ]) ?>

            <?= $this->insert('partials/treat-chooser', [
                'creature' => $creature,
                'treats' => $treats ?? [],
                // Marks this species' favourite in the list. The dislike is
                // deliberately not passed — that one is a surprise.
                'favouriteItemId' => $species?->favouriteItemId,
            ]) ?>
        <?php endif; ?>
    </div>
</article>
