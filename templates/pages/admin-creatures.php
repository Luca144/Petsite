<?php
/**
 * The species list — every creature kind, and the door to a new one.
 *
 * @package Felkyo\Templates
 *
 * Rendered by AdminCreaturesController (Artist role). One card per species;
 * a draft says IN WORDS what it still needs, so this list doubles as the
 * artist's to-do list. Clicking a card opens its one-page editor.
 *
 * Variables: $cards (arrays of species => Species, missing => string[]),
 * $stageNames (string[] — the first is the "face" stage shown on cards).
 */
$this->layout('layout', ['title' => 'Creatures — Felkyo Creatures']);

$faceStage = $stageNames[0] ?? 'baby';
?>

<section class="admin-panel">
    <header class="admin-panel__header card">
        <h2>Creatures</h2>
        <p>
            One page per species: its pictures, words, numbers and whether
            players can meet it yet. Drafts are yours alone until you publish.
        </p>
    </header>

    <div class="card admin-species__new">
        <h3>Start a new species</h3>
        <form method="post" action="/admin/creatures/create" class="admin-species__new-form">
            <?= $this->csrf_field() ?>
            <div class="field">
                <label class="field__label" for="name">Its name</label>
                <input class="field__input" type="text" id="name" name="name" required
                       placeholder="e.g. Mossy Wumple">
            </div>
            <button class="btn btn--primary" type="submit">Start it (as a draft)</button>
        </form>
    </div>

    <?php if (empty($cards)): ?>
        <div class="card"><p>No species yet — the first one starts above.</p></div>
    <?php else: ?>
        <ul class="admin-species__grid">
            <?php foreach ($cards as $card): ?>
                <?php $species = $card['species']; ?>
                <li>
                    <?php /* The whole card is the link — the tap target is the
                             card, not a word inside it. */ ?>
                    <a class="admin-species__card card" href="/admin/creatures/<?= $this->e((string) $species->id) ?>">
                        <span class="admin-species__face">
                            <img class="pixelated" src="<?= $this->e($species->spriteUrlFor($faceStage)) ?>"
                                 alt="" loading="lazy">
                        </span>
                        <span class="admin-species__name"><?= $this->e($species->name) ?></span>
                        <?php /* Status as a WORD, never colour alone. */ ?>
                        <span class="admin-species__status admin-species__status--<?= $species->isPublished() ? 'published' : 'draft' ?>">
                            <?= $species->isPublished() ? 'published' : 'draft' ?>
                        </span>
                        <?php if ($card['missing'] !== []): ?>
                            <span class="admin-species__missing">
                                still needs: <?= $this->e(implode(', ', $card['missing'])) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
