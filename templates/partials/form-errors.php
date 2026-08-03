<?php
/**
 * Reusable "please fix these" error summary for forms.
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: a small partial that shows a list of validation messages above a
 * form. Insert it with $this->insert('partials/form-errors', ['errors' => $errors]).
 *
 * role="alert" makes a screen reader announce the errors as soon as they appear.
 * The errors are conveyed as TEXT (a titled list), never by colour alone, so the
 * meaning is clear to everyone (accessibility rule).
 *
 * $errors is an array of plain message strings; if it is empty, nothing renders.
 */
?>
<?php if (!empty($errors)): ?>
    <div class="form-errors" role="alert">
        <p class="form-errors__title">Please fix the following:</p>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= $this->e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
