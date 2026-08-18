<?php
/**
 * The owner's treat chooser: pick one thing, give it to the creature.
 *
 * @package Felkyo\Templates
 *
 * Insert it with:
 *   $this->insert('partials/treat-chooser', ['creature' => $creature, 'treats' => $treats]);
 * where $treats is an array of OwnedItemStack (see
 * InventoryRepository::findTreatsForUser).
 *
 * OWNER ONLY. The caller decides that — this partial draws the form and does not
 * check anything. FeedingService is what actually refuses a stranger, so the
 * decision is never left to a template.
 *
 * RADIO CARDS, NEVER A DROPDOWN (CLAUDE.md section 8). With two or three treats a
 * dropdown would hide the choice behind a tap and gain nothing — and it could not
 * show what each treat DOES, which is the part that makes choosing interesting.
 * Two treats differing only in strength is a number; three differing in kind is a
 * small decision.
 *
 * THE FAVOURITE IS MARKED; THE DISLIKE IS NOT. A creature's favourite treat is
 * shown with a star, because knowing it is what lets somebody act on it — hiding
 * useful information to make a puzzle is the demanding option, and this site picks
 * the kind one. The dislike stays unmarked on purpose: finding out that your
 * creature pulls a face at chamomile is a small delight the first time, and it
 * costs nothing to discover, because a disliked treat still helps a little.
 *
 * $favouriteItemId is the species' favourite, or null. Passed in by the caller so
 * this partial never goes looking for anything.
 */
$favouriteItemId = $favouriteItemId ?? null;
?>
<?php if (!empty($treats)): ?>
    <form class="creature__feed" method="post"
          action="/creature/<?= $this->e((string) $creature->id) ?>/feed">
        <?= $this->csrf_field() ?>
        <fieldset class="creature__treats">
            <legend class="field__label">Give <?= $this->e($creature->name) ?> a treat</legend>

            <?php foreach ($treats as $index => $stack): ?>
                <label class="creature__treat">
                    <?php /* The first is pre-selected so the form is never
                             submittable with nothing chosen — which would be a
                             refusal a player could not have predicted. */ ?>
                    <input type="radio" name="item_id"
                           value="<?= $this->e((string) $stack->item->id) ?>"
                           <?= $index === 0 ? 'checked' : '' ?>>
                    <span class="creature__treat-body">
                        <span class="creature__treat-name">
                            <?= $this->e($stack->item->name) ?>
                            <?php /* "×3" is quick to scan and says nothing to a
                                     screen reader, so the readable version is
                                     carried alongside and the symbol is hidden.
                                     Both come from the same number. */ ?>
                            <span aria-hidden="true">&times;<?= $this->e((string) $stack->quantity) ?></span>
                            <span class="visually-hidden">, <?= $this->e((string) $stack->quantity) ?> owned</span>

                            <?php if ($favouriteItemId === $stack->item->id): ?>
                                <?php /* A star AND the word, never the star alone —
                                         an icon has to be learnt before it means
                                         anything (CLAUDE.md section 8). */ ?>
                                <span class="creature__treat-loved">
                                    <span aria-hidden="true">&#9733;</span> favourite
                                </span>
                            <?php endif; ?>
                        </span>

                        <?php /* What it does, in words. Both numbers appear when
                                 both apply, so a restful treat is visibly a
                                 different KIND of thing from a cheering one. */ ?>
                        <span class="creature__treat-effect">
                            <?php if ($stack->item->happinessBonus > 0): ?>
                                +<?= $this->e((string) $stack->item->happinessBonus) ?> happiness
                            <?php endif; ?>
                            <?php if ($stack->item->happinessBonus > 0 && $stack->item->energyBonus > 0): ?>
                                &middot;
                            <?php endif; ?>
                            <?php if ($stack->item->energyBonus > 0): ?>
                                +<?= $this->e((string) $stack->item->energyBonus) ?> energy
                            <?php endif; ?>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <button class="btn btn--secondary" type="submit">Feed</button>
    </form>
<?php else: ?>
    <?php /* Never a dead end (golden rule 3): an empty satchel says where treats
             come from and links there, rather than the section simply not being
             here and leaving somebody to wonder whether feeding exists. */ ?>
    <p class="empty-state">
        You have no treats for <?= $this->e($creature->name) ?> just now.
        <a href="/shop">The village store</a> sells them, and they turn up
        while <a href="/explore">exploring</a>.
    </p>
<?php endif; ?>
