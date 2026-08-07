<?php
/**
 * A creature's guestbook — the signatures, and the form to leave one.
 *
 * @package Felkyo\Templates
 *
 * Included by pages/creature.php. Kept as its own partial so the creature page
 * stays comfortably inside the 200-line template limit (CLAUDE.md section 3).
 *
 * THE IMPORTANT DETAIL: visitors do not type a message, they CHOOSE one. The
 * chooser is built from radio buttons styled as cards — never a dropdown, which is
 * forbidden (CLAUDE.md section 8). Real radio inputs mean the whole thing works
 * with a keyboard and is announced correctly by screen readers for free; the CSS
 * only changes how they look, never how they behave.
 *
 * Variables:
 *   $creature  (Felkyo\Creatures\Creature)
 *   $guestbook (array) — ['entries' => GuestbookEntry[], 'messages' => GuestbookMessages,
 *                         'chosenKey' => string|null], built by GuestbookPanel
 *   $canSign   (bool)  — whether this visitor may sign (i.e. is logged in)
 */
$messages = $guestbook['messages'];
$entries = $guestbook['entries'];
$chosenKey = $guestbook['chosenKey'];
?>

<h2 class="panel-label">Guestbook</h2>

<div class="card">
    <?php if (empty($entries)): ?>
        <p class="empty-state">
            Nobody has signed <?= $this->e($creature->name) ?>&rsquo;s guestbook yet.
        </p>
    <?php else: ?>
        <ul class="guestbook-entries">
            <?php foreach ($entries as $entry): ?>
                <li class="guestbook-entry">
                    <!-- The stored value is only a message KEY; the text comes from
                         the catalogue, so rewording a message updates every entry
                         that uses it. -->
                    <p class="guestbook-entry__message">
                        <?= $this->e($messages->textFor($entry->messageKey)) ?>
                    </p>
                    <p class="guestbook-entry__meta">
                        &mdash; <?= $this->e($entry->authorUsername) ?>
                        <?php if ($entry->createdAt !== null): ?>
                            &middot; <?= $this->e(date('j M Y', (int) strtotime($entry->createdAt))) ?>
                        <?php endif; ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($canSign): ?>
        <form method="post" action="/creature/<?= $this->e((string) $creature->id) ?>/guestbook"
              class="guestbook-form">
            <?= $this->csrf_field() ?>

            <!-- A fieldset with a legend is the correct grouping for a set of radio
                 buttons: screen readers read the legend before the options, so the
                 question is clear without relying on visual layout. -->
            <fieldset class="guestbook-choices">
                <legend class="guestbook-choices__legend">
                    <?php if ($chosenKey === null): ?>
                        Leave a message
                    <?php else: ?>
                        Your message (you can change it once a day)
                    <?php endif; ?>
                </legend>

                <!-- The options sit in their own wrapper because a <legend> does not
                     take part in a grid layout reliably across browsers. Laying out
                     this div instead of the fieldset keeps the markup correct AND
                     the layout predictable. -->
                <div class="guestbook-choices__grid">
                    <?php foreach ($messages->all() as $key => $text): ?>
                        <label class="guestbook-choice">
                            <input class="guestbook-choice__input" type="radio" name="message_key"
                                   value="<?= $this->e($key) ?>"
                                   <?= $key === $chosenKey ? 'checked' : '' ?>>
                            <span class="guestbook-choice__text"><?= $this->e($text) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <button class="btn btn--secondary" type="submit">
                <?= $chosenKey === null ? 'Sign the guestbook' : 'Change my message' ?>
            </button>
        </form>
    <?php else: ?>
        <p class="auth-alt">
            <a href="/login">Log in</a> to sign <?= $this->e($creature->name) ?>&rsquo;s guestbook.
        </p>
    <?php endif; ?>
</div>
