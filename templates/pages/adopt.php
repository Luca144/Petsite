<?php
/**
 * The daily-adoption page.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdoptionController. Shows the adopt button when the player may
 * adopt today, or a "come back tomorrow" note when they have already adopted.
 *
 * Variables: $canAdopt (bool); $flash (string|null).
 */
$this->layout('layout', ['title' => 'Adopt a creature — Felkyo Creatures']);
?>

<?php if (!empty($flash)): ?>
    <p class="flash" role="status"><?= $this->e($flash) ?></p>
<?php endif; ?>

<section class="card auth-card">
    <h2>Adopt a creature</h2>
    <p>
        Once a day, a new creature comes looking for a home. Will you take one in?
        You never quite know who you&rsquo;ll meet.
    </p>

    <?php if ($canAdopt): ?>
        <form method="post" action="/adopt">
            <?= $this->csrf_field() ?>
            <button class="btn btn--primary" type="submit">Adopt today&rsquo;s creature</button>
        </form>
    <?php else: ?>
        <p class="empty-state">You&rsquo;ve already adopted today &mdash; come back tomorrow!</p>
    <?php endif; ?>

    <p class="auth-alt"><a href="/creatures">Back to your creatures</a>.</p>
</section>
