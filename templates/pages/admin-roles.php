<?php
/**
 * The roles screen — who is staff, and handing roles out or back.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminRolesController, owner-only (the gate enforces that; this
 * page just draws). Two halves: who currently holds what (each role a chip
 * with its own take-away button), and the grant form (a typed exact username
 * — deliberately not a browsable player list — plus the role as radio cards,
 * never a dropdown).
 *
 * Variables: $assignments (rows from RoleRepository::allAssignments()),
 * $allRoles (Role[]).
 */
$this->layout('layout', ['title' => 'Roles — Felkyo Creatures']);
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Roles</h2>
        <p>
            Who can do what. A person can hold several roles; only an owner
            can change them, and every change is written to the
            <a href="/admin/audit">audit log</a>.
        </p>
    </header>

    <div class="card admin-roles__holders">
        <h3>Who holds what</h3>

        <?php if (empty($assignments)): ?>
            <p>Nobody holds a role yet.</p>
        <?php else: ?>
            <ul class="admin-roles__list">
                <?php
                /* One line per person, their roles as chips beside the name.
                   The rows arrive sorted by name, one row per (person, role),
                   so we group them as we walk: a new name starts a new line. */
                $byUser = [];
                foreach ($assignments as $assignment) {
                    $byUser[$assignment['username']][] = $assignment;
                }
                ?>
                <?php foreach ($byUser as $username => $userAssignments): ?>
                    <li class="admin-roles__holder">
                        <span class="admin-roles__name"><?= $this->e($username) ?></span>
                        <div class="admin-roles__chips">
                            <?php foreach ($userAssignments as $assignment): ?>
                                <div class="admin-roles__chip">
                                    <?= $this->e($assignment['role']->label()) ?>
                                    <?php /* Each chip carries its own take-away
                                             form: revoking is one tap on the
                                             exact thing being revoked, with the
                                             pair (who, which role) fixed by the
                                             server-rendered hidden fields — and
                                             re-checked server-side anyway. */ ?>
                                    <form method="post" action="/admin/roles/revoke" class="admin-roles__revoke">
                                        <?= $this->csrf_field() ?>
                                        <input type="hidden" name="username" value="<?= $this->e($username) ?>">
                                        <input type="hidden" name="role" value="<?= $this->e($assignment['role']->value) ?>">
                                        <button type="submit"
                                                aria-label="Take the <?= $this->e($assignment['role']->label()) ?> role away from <?= $this->e($username) ?>">
                                            take away
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card admin-roles__grant">
        <h3>Hand out a role</h3>

        <form method="post" action="/admin/roles/grant">
            <?= $this->csrf_field() ?>

            <div class="field">
                <label class="field__label" for="username">Their account name, exactly</label>
                <input class="field__input" type="text" id="username" name="username"
                       autocomplete="off" required>
            </div>

            <fieldset class="admin-roles__choices">
                <legend class="field__label">Which role</legend>
                <?php foreach ($allRoles as $role): ?>
                    <?php /* Radio cards, not a dropdown (locked UI rule). The
                             whole label is the tap target, and each role says
                             in a sentence what is being handed over. */ ?>
                    <label class="admin-roles__choice">
                        <input type="radio" name="role" value="<?= $this->e($role->value) ?>" required>
                        <span class="admin-roles__choice-name"><?= $this->e($role->label()) ?></span>
                        <span class="admin-roles__choice-sentence"><?= $this->e($role->description()) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <button class="btn btn--primary" type="submit">Hand it over</button>
        </form>
    </div>
</section>
