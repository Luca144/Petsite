<?php
/**
 * The list of explorable areas.
 *
 * @package Felkyo\Templates
 *
 * Rendered by ExplorationController::index. Each area is a tile linking to its
 * scene. Right now there is one area; more are added by config, not code.
 *
 * Variable: $areas — the areas config (slug => ['name' => ..., 'description' => ...]).
 */
$this->layout('layout', ['title' => 'Explore — Felkyo Creatures']);
?>

<h2 class="panel-label">Where will you wander?</h2>
<p>Search the quiet places of Felkyo. You might come home with a new friend.</p>

<div class="tile-grid">
    <?php foreach ($areas as $slug => $area): ?>
        <a class="tile" href="/explore/<?= $this->e($slug) ?>">
            <span aria-hidden="true">&#127795;</span>
            <span><?= $this->e($area['name']) ?></span>
        </a>
    <?php endforeach; ?>
</div>
