<?php
/**
 * The theme editor's second form: the theme's style — letters, corners,
 * frames, glow, what drifts down the page, and its own background and
 * banner pictures.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/admin-theme-edit.php. Every choice is a row of radio
 * pills (never a dropdown — CLAUDE.md §9), built from ThemeStyle's lists,
 * so adding an option there adds it here with no template change.
 *
 * Each font option is written IN that font, so the artist sees what she's
 * choosing; the shortlist's fonts are loaded for this page only.
 *
 * Variables: $theme (Theme), $siteArt (ArtFile[] of kind Site).
 */
use Felkyo\Themes\ThemeStyle;

$fontFamilies = [];
foreach ([ThemeStyle::HEADING_FONTS, ThemeStyle::BODY_FONTS] as $fontList) {
    foreach ($fontList as [, , $family]) {
        if ($family !== null) {
            $fontFamilies[$family] = true;
        }
    }
}
$fontLists = ['heading_font' => ThemeStyle::HEADING_FONTS, 'body_font' => ThemeStyle::BODY_FONTS];
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= $this->e(implode('&family=', array_keys($fontFamilies))) ?>&display=swap">

<form class="card theme-style" method="post" action="/admin/themes/<?= $this->e((string) $theme->id) ?>/style">
    <?= $this->csrf_field() ?>
    <h3>Style</h3>
    <p class="library-hint">Letters, corners and pictures. Everything here is picked from lists we&rsquo;ve
       checked work on phones and stay readable.</p>
    <?php if ($theme->easyReading): ?>
        <p class="library-hint"><b>Note:</b> this theme has easy-reading letters switched on, which
           uses the Lexend font everywhere &mdash; so the letters chosen below won&rsquo;t show
           until that box is unticked.</p>
    <?php endif; ?>

    <?php foreach (ThemeStyle::lists() as $name => $options): ?>
        <fieldset class="theme-style__choice">
            <legend class="field__label"><?= $this->e(ThemeStyle::NAMES[$name]) ?></legend>
            <div class="theme-style__pills">
                <?php foreach ($options as $value => $option): ?>
                    <?php /* A font option shows itself in its own letters. The stack
                             comes from ThemeStyle's constant, never from input. */
                    $fontStack = isset($fontLists[$name]) ? $option[1] : null; ?>
                    <label class="theme-style__pill"<?= $fontStack !== null ? ' style="font-family: ' . $this->e($fontStack) . '"' : '' ?>>
                        <input type="radio" name="<?= $this->e($name) ?>" value="<?= $this->e($value) ?>"
                               <?= $theme->style->chosen($name) === $value ? 'checked' : '' ?>>
                        <span>
                            <?php if ($name === 'motes' && $option[1] !== []): ?>
                                <span aria-hidden="true"><?= $option[1][0] ?></span>
                            <?php endif; ?>
                            <?= $this->e($option[0]) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <?php foreach (['background_art_id' => ['Page background', $theme->backgroundArtId], 'banner_art_id' => ['Banner at the top', $theme->bannerArtId]] as $field => [$label, $chosenId]): ?>
        <fieldset class="theme-style__choice">
            <legend class="field__label"><?= $this->e($label) ?></legend>
            <div class="theme-style__pictures">
                <label class="theme-style__picture">
                    <input type="radio" name="<?= $this->e($field) ?>" value="" <?= $chosenId === null ? 'checked' : '' ?>>
                    <span class="theme-style__picture-none">The site&rsquo;s own</span>
                </label>
                <?php foreach ($siteArt as $picture): ?>
                    <label class="theme-style__picture">
                        <input type="radio" name="<?= $this->e($field) ?>" value="<?= $this->e((string) $picture->id) ?>"
                               <?= $chosenId === $picture->id ? 'checked' : '' ?>>
                        <img src="/art/<?= $this->e($picture->storedName) ?>" alt="<?= $this->e($picture->originalName) ?>" loading="lazy">
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>
    <?php if ($siteArt === []): ?>
        <p class="library-hint">To use your own background or banner, upload it in
           <a href="/admin/pictures">Pictures</a> as &ldquo;Site art&rdquo;.</p>
    <?php endif; ?>

    <button class="btn btn--primary" type="submit">Save the style</button>
</form>
