<?php
/**
 * The small "report this" link shown beside anything a player wrote.
 *
 * @package Felkyo\Templates
 *
 * Insert it with:
 *   $this->insert('partials/report-link', ['subject' => 'creature_bio', 'id' => 42]);
 *
 * WHY IT IS ALWAYS VISIBLE rather than hidden behind a hover or a menu: nothing
 * important on this site lives in hover alone (golden rule 6), and the report
 * control is the most important thing on the page for the one person who needs
 * it. It is deliberately quiet, not hidden — small and grey, but always there and
 * always reachable by keyboard.
 *
 * It is only shown to somebody logged in; a report from nobody cannot be
 * followed up. The caller decides that, so this partial stays dumb.
 */
?>
<a class="report-link" href="/report/<?= $this->e($subject) ?>/<?= $this->e((string) $id) ?>">
    <span aria-hidden="true">⚑</span> Report
</a>
