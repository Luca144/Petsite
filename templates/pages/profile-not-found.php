<?php
/**
 * Shown when somebody opens a profile that does not exist.
 *
 * @package Felkyo\Templates
 *
 * Never a dead end (golden rule 3): it says what happened and offers somewhere to
 * go, rather than leaving a bare 404.
 *
 * It also says the SAME thing whether the name never existed or the account is
 * simply not findable. Distinguishing them would let somebody test names one at a
 * time to learn who is here — see the enumeration note in CLAUDE.md section 6.
 */
$this->layout('layout', ['title' => 'No one by that name — Felkyo Creatures']);
?>

<h2 class="panel-label">No one by that name</h2>

<p class="empty-state">
    There&rsquo;s nobody here with that name — the spelling might be a little off,
    or they may have wandered on.
</p>

<div class="button-row">
    <a class="btn btn--secondary" href="/browse">Browse recent creatures</a>
    <a class="btn btn--secondary" href="/">Back to the village</a>
</div>
