<?php
/**
 * The area's scene and its search spots, edited together.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/admin-area-edit.php. THE NUMBER FIELDS ARE THE FORM:
 * paired x/y percentages, one row per spot, plus three blank rows so spots
 * can be added without JavaScript (blank pairs are skipped on save). The
 * scene preview above them is the click-to-place layer's canvas
 * (area-spots.js): clicking it fills a blank pair; clicking a marker
 * clears its pair. Keyboard and no-JS users have everything in the
 * numbers — the standing rule: placing works by tapping AND by keyboard,
 * clicking never the only way.
 *
 * Variables: $area (Area).
 */
$sceneStyle = $area->backgroundUrl() !== null
    ? ' style="--scene-bg: url(\'' . $this->e($area->backgroundUrl()) . '\')"'
    : '';
?>
<div class="card">
    <h3>The scene &amp; its spots</h3>

    <div class="admin-scene<?= $area->backgroundUrl() !== null ? ' admin-scene--painted' : '' ?>"<?= $sceneStyle ?>
         data-spot-scene
         aria-hidden="true">
        <?php /* Markers are drawn by area-spots.js from the number fields;
                 aria-hidden because the FIELDS below are the accessible
                 truth — this box is a pointing convenience. */ ?>
    </div>
    <p class="field__hint" data-spot-hint hidden>
        Click the scene to place a spot; click a marker to remove it. The
        numbers below update as you go — they are what gets saved.
    </p>

    <div class="admin-species__actions">
        <a class="btn btn--secondary" href="/admin/areas/<?= $this->e((string) $area->id) ?>/picture">
            Choose the scene picture
        </a>
        <?php if ($area->backgroundUrl() === null): ?>
            <p class="field__hint">No picture yet — players see the site&rsquo;s cosy lamplight glow instead.</p>
        <?php endif; ?>
    </div>

    <form method="post" action="/admin/areas/<?= $this->e((string) $area->id) ?>/spots">
        <?= $this->csrf_field() ?>
        <fieldset class="admin-scene__rows" data-spot-rows>
            <legend class="field__label">The spots — left and down, in % of the scene (blank rows are ignored)</legend>
            <?php
            // The current spots, then three blank rows for adding more.
            $rows = $area->spots;
            for ($blank = 0; $blank < 3; $blank++) {
                $rows[] = ['x' => '', 'y' => ''];
            }
            ?>
            <?php foreach ($rows as $index => $spot): ?>
                <div class="admin-scene__row">
                    <label>
                        <span class="visually-hidden">Spot <?= $index + 1 ?>, from the left</span>
                        <input class="field__input" type="number" name="spot_x[]" min="0" max="100"
                               value="<?= $this->e((string) $spot['x']) ?>" placeholder="x">
                    </label>
                    <label>
                        <span class="visually-hidden">Spot <?= $index + 1 ?>, from the top</span>
                        <input class="field__input" type="number" name="spot_y[]" min="0" max="100"
                               value="<?= $this->e((string) $spot['y']) ?>" placeholder="y">
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <button class="btn btn--primary" type="submit">Save the spots</button>
    </form>
</div>
