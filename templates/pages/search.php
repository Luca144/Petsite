<?php
/**
 * Finding another player by name.
 *
 * @package Felkyo\Templates
 *
 * Rendered by SearchController::show(). Variables: $query, $results (list of
 * ['username','avatarPath','avatarName']), $hasSearched, $notice, $minimumLength.
 *
 * WHAT IS DELIBERATELY NOT HERE: any way to browse. No "all players", no "newest
 * members", no letters of the alphabet to click through. You can find somebody
 * whose name you already half know, and that is the whole feature.
 *
 * The results show only what is already on the profile page anyway — a name and
 * an avatar. Nothing is revealed by finding somebody that would not be revealed
 * by visiting them.
 */
$this->layout('layout', ['title' => 'Find a player — Felkyo Creatures']);
?>

<h2 class="panel-label">Find a player</h2>

<form class="search-form" method="get" action="/players">
    <label class="profile-form__label" for="q">Who are you looking for?</label>
    <div class="search-form__row">
        <input class="search-form__input"
               type="search"
               id="q"
               name="q"
               value="<?= $this->e($query) ?>"
               maxlength="30"
               autocomplete="off"
               aria-describedby="search-help">
        <button class="btn btn--primary" type="submit">Search</button>
    </div>
    <p class="profile-form__help" id="search-help">
        Type the start of their name &mdash; at least <?= $this->e((string) $minimumLength) ?> letters.
    </p>
</form>

<?php if ($notice !== null): ?>
    <p class="flash" role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>

<?php if ($hasSearched && $notice === null): ?>
    <?php if (empty($results)): ?>
        <?php /* Never a dead end (golden rule 3): the empty result says what to try
                 instead, and quietly explains that not everyone can be found —
                 which is true and saves somebody thinking the site is broken. */ ?>
        <p class="empty-state">
            Nobody here starts with &ldquo;<?= $this->e($query) ?>&rdquo;.
            Check the spelling &mdash; and note that some players choose not to be findable.
        </p>
    <?php else: ?>
        <ul class="search-results">
            <?php foreach ($results as $player): ?>
                <li>
                    <a class="search-result" href="/player/<?= $this->e(rawurlencode($player['username'])) ?>">
                        <img class="search-result__avatar pixelated"
                             src="<?= $this->e($player['avatarPath']) ?>"
                             alt="" width="40" height="40">
                        <span class="search-result__name"><?= $this->e($player['username']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>
