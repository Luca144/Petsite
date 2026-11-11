<?php
/**
 * Editing one theme (M4.1, M4.2): a colour picker per token, a live preview
 * card, and a readability list that updates as colours change.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminThemesController::show. The live checking is
 * theme-editor.js; it reads the SAME rules the server uses, handed over in
 * data-rules, so the page and the server can't disagree. Without scripting
 * the page still works: save, and the server's answer lists the problems.
 *
 * Variables: $theme (Theme), $palette (full token => #RRGGBB),
 * $problems (string[] from the server's own check).
 */
use Felkyo\Themes\ThemeTokens;

$this->layout('layout', ['title' => $theme->name . ' — Themes — Felkyo Creatures']);
$id = (string) $theme->id;
$rulesJson = json_encode(array_map(
    static fn (array $rule): array => ['text' => $rule[0], 'background' => $rule[1], 'where' => $rule[2]],
    ThemeTokens::RULES
));
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2><?= $this->e($theme->name) ?></h2>
        <p class="admin-species__statusline">
            <span class="admin-species__status admin-species__status--<?= $theme->isPublished() ? 'published' : 'draft' ?>">
                <?= $theme->isPublished() ? 'published' : 'draft' ?>
            </span>
        </p>
        <div class="admin-species__actions">
            <form method="post" action="/admin/themes/<?= $this->e($id) ?>/<?= $theme->isPublished() ? 'unpublish' : 'publish' ?>">
                <?= $this->csrf_field() ?>
                <button class="btn <?= $theme->isPublished() ? 'btn--secondary' : 'btn--primary' ?>" type="submit">
                    <?= $theme->isPublished() ? 'Back to draft' : 'Publish' ?>
                </button>
            </form>
        </div>
        <p><a href="/admin/themes">&larr; all themes</a></p>
    </header>

    <form class="card theme-editor" method="post" action="/admin/themes/<?= $this->e($id) ?>/save"
          data-rules="<?= $this->e((string) $rulesJson) ?>" data-minimum="<?= $this->e((string) ThemeTokens::MINIMUM_RATIO) ?>">
        <?= $this->csrf_field() ?>
        <div class="field">
            <label class="field__label" for="theme-name">Name</label>
            <input class="field__input" type="text" id="theme-name" name="name" maxlength="40" value="<?= $this->e($theme->name) ?>" required>
        </div>

        <div class="theme-editor__colours">
            <?php foreach (ThemeTokens::EDITABLE as $token => [$label]): ?>
                <label class="theme-editor__colour">
                    <input type="color" name="colour_<?= $this->e($token) ?>" data-token="<?= $this->e($token) ?>"
                           value="<?= $this->e(strtolower($palette[$token])) ?>">
                    <span><?= $this->e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <label class="creature-keeping__sure">
            <input type="checkbox" name="easy_reading" value="1" <?= $theme->easyReading ? 'checked' : '' ?>>
            Easy-reading letters and spacing (the dyslexia-friendly mode)
        </label>

        <h3>How it reads</h3>
        <?php /* Filled in live by theme-editor.js; the server's own verdict is
                 printed here first, so it is right even without scripting. */ ?>
        <ul class="theme-editor__checks" aria-live="polite">
            <?php if ($problems === []): ?>
                <li class="theme-editor__ok">&#10003; Readable everywhere.</li>
            <?php else: ?>
                <?php foreach ($problems as $problem): ?>
                    <li class="theme-editor__bad">&#10007; <?= $this->e($problem) ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <?php /* The preview redefines the colour tokens on itself, so everything
                 inside it uses the theme being edited. The values are cleaned
                 #RRGGBB strings (ThemeTokens), safe in a style attribute. */
        $previewStyle = "";
        foreach ($palette as $token => $value) {
            $previewStyle .= "--" . $token . ": " . $value . "; ";
        }
        ?>
        <div class="theme-editor__preview" aria-hidden="true" style="<?= $this->e($previewStyle) ?>">
            <div class="theme-editor__preview-page">
                <strong>A creature page</strong>
                <span>Body text sits here, <u>with a link</u>.</span>
                <small>Quieter text, like a date.</small>
                <span class="theme-editor__preview-card">Inside a card, <u>another link</u>.</span>
                <span class="theme-editor__preview-button">A button</span>
                <span class="theme-editor__preview-dark">Gold on a dark panel</span>
            </div>
        </div>

        <button class="btn btn--primary" type="submit">Save the colours</button>
    </form>

    <?= $this->insert('partials/admin-theme-style', ['theme' => $theme, 'siteArt' => $siteArt ?? []]) ?>

    <div class="card">
        <form method="post" action="/admin/themes/<?= $this->e($id) ?>/delete"
              onsubmit="return confirm('Delete this theme? Anyone using it goes back to the normal look.');">
            <?= $this->csrf_field() ?>
            <button class="btn--quiet" type="submit">delete this theme</button>
        </form>
    </div>
</section>
<script src="<?= \Felkyo\Http\AssetUrl::versioned('/js/theme-editor.js') ?>" defer></script>
