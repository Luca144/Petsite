<?php
/**
 * Choosing how Felkyo looks for you (M4.1, M4.3).
 *
 * @package Felkyo\Templates
 *
 * Rendered by ThemeController::show. Every option is a radio card with
 * swatches of its colours AND its name in words — never colour alone. No
 * dropdown (CLAUDE.md §9). "Felkyo's look" means no theme of your own:
 * you see whatever default the artist chose in the panel (Site look).
 *
 * Variables: $themes (Theme[], published), $chosenId (?int).
 */
use Felkyo\Themes\ThemeTokens;

$this->layout('layout', ['title' => 'Your theme — Felkyo Creatures']);

// The swatches each card shows: surface, cards, text, accent, sparkle.
$swatchTokens = ['parchment', 'panel', 'plum', 'purple', 'gold'];
$options = [['id' => 0, 'name' => 'Felkyo\'s look', 'note' => 'whatever the site is wearing — it may change with the seasons', 'palette' => ThemeTokens::paletteWith([])]];
foreach ($themes as $theme) {
    $options[] = [
        'id' => $theme->id,
        'name' => $theme->name,
        'note' => $theme->easyReading ? 'easier-to-read letters and spacing' : '',
        'palette' => ThemeTokens::paletteWith($theme->tokens),
    ];
}
?>

<h2 class="panel-label"><?= $this->t('themes.your-theme', 'Your theme') ?></h2>
<p><?= $this->t('themes.pick-the-colours-felkyo-wears', 'Pick the colours Felkyo wears for you. Only you see your choice. Every theme here is checked to stay easy to read.') ?></p>

<form method="post" action="/themes">
    <?= $this->csrf_field() ?>
    <fieldset class="theme-picker">
        <legend class="visually-hidden"><?= $this->t('themes.themes', 'Themes') ?></legend>
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
    <button class="btn btn--primary" type="submit"><?= $this->t('themes.wear-this-theme', 'Wear this theme') ?></button>
</form>
