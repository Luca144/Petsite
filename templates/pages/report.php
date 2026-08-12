<?php
/**
 * Choosing a reason for a report.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ReportController::show(). Variables: $subject (ReportSubject),
 * $subjectId (int), $reasons (ReportReason[], most serious first).
 *
 * THE ORDER IS DELIBERATE. Somebody frightened should not have to read past
 * "there's a rude word" to find the one that describes what is happening to them.
 *
 * NO FREE-TEXT BOX. A "tell us more" field would be one player writing words that
 * another player reads, which is the one thing this site's design does not have.
 */
$this->layout('layout', ['title' => 'Report something — Felkyo Creatures']);
?>

<h2 class="panel-label">Report <?= $this->e($subject->label()) ?></h2>

<p class="report-intro">
    Thank you for telling us. Choose whichever is closest — you don&rsquo;t have to
    get it exactly right, and nobody will be told that it was you.
</p>

<form method="post" action="/report" class="report-form">
    <?= $this->csrf_field() ?>
    <input type="hidden" name="subject" value="<?= $this->e($subject->value) ?>">
    <input type="hidden" name="subject_id" value="<?= $this->e((string) $subjectId) ?>">

    <fieldset class="report-reasons">
        <legend class="visually-hidden">What is wrong?</legend>

        <?php foreach ($reasons as $reason): ?>
            <?php /* Each reason is its own submit button. One tap sends the report
                     — no choosing then confirming, because an upset child should
                     not have to complete a two-step form. */ ?>
            <button class="report-reason" type="submit" name="reason"
                    value="<?= $this->e($reason->value) ?>">
                <?= $this->e($reason->label()) ?>
            </button>
        <?php endforeach; ?>
    </fieldset>
</form>

<p class="report-footnote">
    If someone is making you uncomfortable, please also tell a grown-up you trust.
    You can always email us at <b>hello@felkyo.example</b>.
</p>

<div class="button-row">
    <a class="btn btn--secondary" href="/">Never mind, take me back</a>
</div>
