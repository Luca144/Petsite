<?php
/**
 * A staff member reached a panel screen their roles don't open.
 *
 * @package Felkyo\Templates
 *
 * Rendered by the AdminGate with a 403 — only ever for someone who IS staff
 * (players get the plain 404 instead, so the panel never confirms itself to
 * them). This person is a colleague who clicked the wrong thing, so the page
 * names the missing role and offers the way back (golden rule 3: never a
 * dead end).
 *
 * Variables: $requiredRole (Role).
 */
$this->layout('layout', ['title' => 'Not your door — Felkyo Creatures']);
?>

<section class="card auth-card admin-forbidden">
    <h2>Not your door</h2>

    <p>
        This screen belongs to the <strong><?= $this->e($requiredRole->label()) ?></strong>
        role, which your account doesn&rsquo;t hold. If you think it should,
        ask an owner &mdash; roles are theirs to hand out.
    </p>

    <a class="btn btn--primary" href="/admin">Back to the panel</a>
</section>
