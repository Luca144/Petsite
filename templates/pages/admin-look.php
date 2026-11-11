<?php
/**
 * The panel's "Site look" screen: the look everyone sees by default, the
 * order of the sections on creature and player pages, and an announcement
 * line for the top of every page.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminLookController::show (artist role). Each change is its
 * own small form, so one press does one thing and says so. Reordering is
 * up/down buttons, not dragging — it works by keyboard and on a phone.
 *
 * Variables: $look (SiteLook), $themes (published Theme[]).
 */
use Felkyo\Look\SiteLook;
use Felkyo\Look\SiteLookService;
use Felkyo\Themes\ThemeTokens;

$this->layout('layout', ['title' => 'Site look — Felkyo Creatures']);
$swatchTokens = ['parchment', 'panel', 'plum', 'purple', 'gold'];
$options = [['id' => '', 'name' => 'Lamplight', 'note' => 'Felkyo\'s own look', 'palette' => ThemeTokens::paletteWith([])]];
foreach ($themes as $theme) {
    $options[] = ['id' => (string) $theme->id, 'name' => $theme->name, 'note' => '', 'palette' => ThemeTokens::paletteWith($theme->tokens)];
}
$currentDefault = $look->defaultThemeId === null ? '' : (string) $look->defaultThemeId;
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Site look</h2>
        <p>The whole site at once. For colours, letters, corners and pictures, edit a
           <a href="/admin/themes">theme</a> and make it the default here. For small
           fixes there&rsquo;s the <a href="/admin/look/css">fine-tuning CSS</a>.</p>
    </header>

    <form class="card" method="post" action="/admin/look/default-theme">
        <?= $this->csrf_field() ?>
        <h3>The look everyone sees</h3>
        <p class="library-hint">Guests and everyone who hasn&rsquo;t picked their own theme see this one.
           Only published themes are listed.</p>
        <fieldset class="theme-picker">
            <legend class="visually-hidden">Default theme</legend>
            <?php foreach ($options as $option): ?>
                <label class="theme-card">
                    <input class="theme-card__radio" type="radio" name="theme_id" value="<?= $this->e($option['id']) ?>"
                           <?= $currentDefault === $option['id'] ? 'checked' : '' ?>>
                    <span class="theme-card__swatches" aria-hidden="true">
                        <?php foreach ($swatchTokens as $token): ?>
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
        <button class="btn btn--primary" type="submit">Make this the default</button>
    </form>

    <div class="card" id="order">
        <h3>The order of things on a page</h3>
        <?php foreach (SiteLook::PAGE_NAMES as $page => $pageName): ?>
            <h4 class="look-order__page"><?= $this->e($pageName) ?></h4>
            <ol class="look-order">
                <?php $order = $look->orderOf($page); foreach ($order as $position => $section): ?>
                    <li class="look-order__item">
                        <span class="look-order__name"><?= $this->e(SiteLook::SECTIONS[$page][$section]) ?></span>
                        <?php foreach (['up' => '&uarr; up', 'down' => '&darr; down'] as $direction => $arrow): ?>
                            <?php $atEnd = ($direction === 'up' && $position === 0) || ($direction === 'down' && $position === count($order) - 1); ?>
                            <form method="post" action="/admin/look/sections">
                                <?= $this->csrf_field() ?>
                                <input type="hidden" name="page" value="<?= $this->e($page) ?>">
                                <input type="hidden" name="section" value="<?= $this->e($section) ?>">
                                <input type="hidden" name="direction" value="<?= $this->e($direction) ?>">
                                <button class="btn btn--secondary look-order__move" type="submit" <?= $atEnd ? 'disabled' : '' ?>
                                        aria-label="Move <?= $this->e(SiteLook::SECTIONS[$page][$section]) ?> <?= $this->e($direction) ?>"><?= $arrow ?></button>
                            </form>
                        <?php endforeach; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <form method="post" action="/admin/look/sections/reset">
                <?= $this->csrf_field() ?>
                <input type="hidden" name="page" value="<?= $this->e($page) ?>">
                <button class="btn--quiet" type="submit">put <?= $this->e(strtolower($pageName)) ?> back to the site&rsquo;s own order</button>
            </form>
        <?php endforeach; ?>
    </div>

    <form class="card" method="post" action="/admin/look/announcement">
        <?= $this->csrf_field() ?>
        <h3>An announcement on every page</h3>
        <div class="field">
            <label class="field__label" for="look-announcement">One line, shown at the top of every page. Leave it empty for none.</label>
            <input class="field__input" type="text" id="look-announcement" name="announcement"
                   maxlength="<?= SiteLookService::MAX_ANNOUNCEMENT ?>" value="<?= $this->e($look->announcement) ?>"
                   placeholder="e.g. The autumn festival starts on Saturday!">
        </div>
        <button class="btn btn--primary" type="submit">Save the announcement</button>
    </form>
</section>
