<?php
/**
 * Choosing how Felkyo looks for you (M4.1, M4.3).
 *
 * @package Felkyo\Templates
 *
 * Rendered by ThemeController::show. Every option is a radio card with
 * swatches of its colours AND its name in words — never colour alone. No
 * dropdown (CLAUDE.md §9). "Lamplight" is the site's own look (no theme).
 *
 * Variables: $themes (Theme[], published), $chosenId (?int).
 */
use Felkyo\Themes\ThemeTokens;

$this->layout('layout', ['title' => 'Your theme — Felkyo Creatures']);

// The swatches each card shows: surface, cards, text, accent, sparkle.
$swatchTokens = ['parchment', 'panel', 'plum', 'purple', 'gold'];
$options = [['id' => 0, 'name' => 'Lamplight', 'note' => 'the site\'s own look', 'palette' => ThemeTokens::paletteWith([])]];
foreach ($themes as $theme) {
    $options[] = [
        'id' => $theme->id,
        'name' => $theme->name,
        'note' => $theme->easyReading ? 'easier-to-read letters and spacing' : '',
        'palette' => ThemeTokens::paletteWith($theme->tokens),
    ];
}
?>

<h2 class="panel-label">Your theme</h2>
<p>Pick the colours Felkyo wears for you. Only you see your choice. Every theme here is checked to stay easy to read.</p>

<form method="post" action="/themes">
    <?= $this->csrf_field() ?>
    <fieldset class="theme-picker">
        <legend class="visually-hidden">Themes</legend>
        <?php foreach ($options as $option): ?>
            <label class="theme-card">
                <input class="theme-card__radio" type="radio" name="theme_id" value="<?= $this->e((string) $option['id']) ?>"
                       <?= ($chosenId ?? 0) === $option['id'] ? 'checked' : '' ?>>
                <span class="theme-card__swatches" aria-hidden="true">
                    <?php foreach ($swatchTokens as $token): ?>
                        <?php /* The swatch colour comes from a cleaned #RRGGBB value
                                 (ThemeTokens::clean), so printing it into a style
                                 attribute is safe. */ ?>
                        <span class="theme-card__swatch" style="background: <?= $this->e($option['palette'][$token]) ?>"></span>
                    <?php endforeach; ?>
                </span>
                <span class="theme-card__name"><?= $this->e($option['name']) ?></span>
                <?php if ($option['note'] !== ''): ?>
                    <span class="theme-card__note"><?= $this->e($option['note']) ?></span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
    <button class="btn btn--primary" type="submit">Wear this theme</button>
</form>
