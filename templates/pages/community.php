<?php
/**
 * The Community page — the creatures of Felkyo, and the people who keep them.
 *
 * @package Felkyo\Templates
 *
 * Rendered by CommunityController. Two tabs on one page:
 *   - "creatures": recently met public creatures (what /browse used to be)
 *   - "people":    find a player by the start of their name
 *
 * Variables: $tab ('creatures'|'users'), $creatureSummaries, $search (a
 * PlayerSearchResult, or null on the creatures tab), $minimumSearchLength.
 *
 * THE TABS ARE LINKS, NOT JAVASCRIPT. Each tab is a real address you can
 * bookmark, share and reach with the back button, and the page works with
 * scripting switched off. aria-current marks the open one for screen readers,
 * and the styling hangs off that same attribute so the look and the announced
 * state can never disagree.
 */
$this->layout('layout', ['title' => 'Community — Felkyo Creatures']);
?>

<h2 class="panel-label">Community</h2>

<nav class="community__tabs" aria-label="Community sections">
    <a class="community__tab" href="/community?tab=creatures"
       <?= $tab === 'creatures' ? 'aria-current="page"' : '' ?>>
        creatures
    </a>
    <a class="community__tab" href="/community?tab=users"
       <?= $tab === 'users' ? 'aria-current="page"' : '' ?>>
        people
    </a>
</nav>

<?php if ($tab === 'creatures'): ?>

    <p>A few of the creatures making their home in Felkyo right now. Say hello &mdash; you can pet any of them.</p>

    <?php if (empty($creatureSummaries)): ?>
        <p class="empty-state">No creatures to show yet. Be the first!</p>
    <?php else: ?>
        <div class="creature-collection">
            <?php foreach ($creatureSummaries as $summary): ?>
                <?= $this->insert('partials/creature-card', ['summary' => $summary]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>

    <?php /* A GET form, because a search belongs in the address: the result can
             then be bookmarked and shared. The hidden "tab" field keeps you on
             this tab when the form submits. */ ?>
    <form class="search-form" method="get" action="/community">
        <input type="hidden" name="tab" value="users">
        <label class="profile-form__label" for="q">Who are you looking for?</label>
        <div class="search-form__row">
            <input class="search-form__input"
                   type="search"
                   id="q"
                   name="q"
                   value="<?= $this->e($search->query) ?>"
                   maxlength="30"
                   autocomplete="off"
                   aria-describedby="search-help">
            <button class="btn btn--primary" type="submit">Search</button>
        </div>
        <p class="profile-form__help" id="search-help">
            Type the start of their name &mdash; at least
            <?= $this->e((string) $minimumSearchLength) ?> letters.
        </p>
    </form>

    <?php if ($search->notice !== null): ?>
        <p class="flash" role="status"><?= $this->e($search->notice) ?></p>
    <?php endif; ?>

    <?php if ($search->hasSearched): ?>
        <?php if (empty($search->players)): ?>
            <?php /* Never a dead end (golden rule 3): say what to try instead, and
                     mention that some players choose not to be findable — which is
                     true, and saves somebody thinking the site is broken. */ ?>
            <p class="empty-state">
                Nobody here starts with &ldquo;<?= $this->e($search->query) ?>&rdquo;.
                Check the spelling &mdash; and note that some players choose not to be findable.
            </p>
        <?php else: ?>
            <ul class="search-results">
                <?php foreach ($search->players as $player): ?>
                    <li>
                        <a class="search-result" href="/player/<?= $this->e(rawurlencode($player['username'])) ?>">
                            <img class="search-result__avatar"
                                 src="<?= $this->e($player['avatarPath']) ?>"
                                 alt="" width="40" height="40">
                            <span class="search-result__name"><?= $this->e($player['username']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

<?php endif; ?>
