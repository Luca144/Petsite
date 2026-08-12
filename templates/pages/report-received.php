<?php
/**
 * The thank-you shown after a report is filed.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ReportController::submit(). Variables: $message, $wasReceived.
 *
 * WHY THIS PAGE EXISTS AT ALL. Silence teaches people not to report, and
 * reporting is the main safety mechanism on this site — every filter beneath it
 * only catches the obvious. So the site says, plainly, that it arrived and that a
 * person will read it. It promises nothing else, because nothing else is true yet.
 */
$this->layout('layout', ['title' => 'Thank you — Felkyo Creatures']);
?>

<h2 class="panel-label"><?= $wasReceived ? 'Thank you' : 'We couldn’t do that' ?></h2>

<p class="report-thanks" role="status"><?= $this->e($message) ?></p>

<div class="button-row">
    <a class="btn btn--secondary" href="/">Back to the village</a>
</div>
