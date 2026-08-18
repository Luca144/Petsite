<?php
/**
 * The Community page — browse creatures and find players in one place.
 *
 * @package Felkyo\Templates
 *
 * Rendered by CommunityController. This page combines two features:
 * - "Browse Creatures" tab: recently met creatures from around Felkyo
 * - "Find People" tab: search for players by name
 *
 * Variables: $tab (currently active tab: "creatures" or "users"), $query (search),
 * $creatureSummaries, $foundPlayers, $currentUser, $avatarSet.
 */
$this->layout('layout', ['title' => 'Community — Felkyo Creatures']);

// Simple tab logic: default to "creatures" if not specified or invalid
if (!in_array($tab, ['creatures', 'users'])) {
    $tab = 'creatures';
}
?>

<div class="community__header">
    <h2 class="panel-label">Community</h2>
</div>

<!-- Tab navigation -->
<div class="community__tabs">
    <a class="community__tab<?= $tab === 'creatures' ? ' community__tab--active' : '' ?>"
       href="/community?tab=creatures"
       <?= $tab === 'creatures' ? 'aria-current="page"' : '' ?>>
        Browse Creatures
    </a>
    <a class="community__tab<?= $tab === 'users' ? ' community__tab--active' : '' ?>"
       href="/community?tab=users"
       <?= $tab === 'users' ? 'aria-current="page"' : '' ?>>
        Find People
    </a>
</div>

<!-- Browse Creatures tab -->
<?php if ($tab === 'creatures'): ?>
    <div class="community__panel">
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
    </div>

<!-- Find People tab -->
<?php else: ?>
    <div class="community__panel">
        <form class="search-form" method="get" action="/community">
            <input type="hidden" name="tab" value="users">
            <label class="profile-form__label" for="q">Who are you looking for?</label>
            <div class="search-form__row">
                <input class="search-form__input"
                       type="search"
                       id="q"
                       name="q"
                       value="<?= $this->e($query) ?>"
                       maxlength="30"
                       autocomplete="off"
                       aria-describedby="search-help"
                       placeholder="Start typing a name...">
                <button class="btn btn--primary" type="submit">Search</button>
            </div>
            <p class="profile-form__help" id="search-help">
                Type the start of their name &mdash; at least 3 letters.
            </p>
        </form>

        <?php if (!empty($query)): ?>
            <?php if (empty($foundPlayers)): ?>
                <p class="empty-state">
                    Nobody here starts with "<?= $this->e($query) ?>".
                    Check the spelling &mdash; and note that some players choose not to be findable.
                </p>
            <?php else: ?>
                <ul class="search-results">
                    <?php foreach ($foundPlayers as $player): ?>
                        <li>
                            <a class="search-result" href="/player/<?= $this->e(rawurlencode($player->username)) ?>">
                                <img class="search-result__avatar"
                                     src="<?= $this->e($avatarSet->imagePathFor($player->avatarKey)) ?>"
                                     alt="" width="40" height="40">
                                <span class="search-result__name"><?= $this->e($player->username) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
