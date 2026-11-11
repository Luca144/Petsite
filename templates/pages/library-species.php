<?php
/**
 * One species' page in the library (M5.1–M5.3): how it grows up, its
 * alternate forms, and its book.
 *
 * @package Felkyo\Templates
 *
 * Rendered by LibraryController::species. Public.
 *
 * Variables: $species (Species, published), $stages (string[] in growth
 * order), $forms (SpeciesForm[], published only).
 *
 * THE BOOK IS A <details> ELEMENT. Clicking (or pressing Enter on) the cover
 * opens it — the browser does that by itself, so it works by keyboard, with a
 * screen reader, and with scripting switched off. The opening flourish is
 * CSS only and is switched off for anyone who prefers reduced motion. The
 * story inside is ordinary text, never a picture of text, so it reads on a
 * phone and can be read aloud (build plan M5.2).
 */
$this->layout('layout', ['title' => $species->name . ' — The library — Felkyo Creatures']);

// The long story, split into paragraphs at blank lines so the artist can write
// naturally in the panel and the page still gets real paragraphs.
$lore = trim(str_replace("\r\n", "\n", (string) $species->lore));
$loreParagraphs = $lore === '' ? [] : array_values(array_filter(
    array_map('trim', preg_split('/\n\s*\n/', $lore) ?: []),
    static fn (string $paragraph): bool => $paragraph !== ''
));

// The cover: the library picture if one was uploaded, the grown-up sprite if not.
$coverUrl = $species->libraryArtUrl() ?? $species->spriteUrlFor($stages[count($stages) - 1] ?? 'adult');
?>

<p class="library-back"><a href="/library"><?= $this->t('library-species.back-to-the-shelves', '← back to the shelves') ?></a></p>

<h2 class="panel-label"><?= $this->e($species->name) ?></h2>
<?php if ($species->flavourText !== null && $species->flavourText !== ''): ?>
    <p class="library-flavour"><?= $this->e($species->flavourText) ?></p>
<?php endif; ?>

<section aria-labelledby="growing-up">
    <h3 id="growing-up" class="library-section"><?= $this->t('library-species.growing-up', 'Growing up') ?></h3>
    <ol class="library-stages">
        <?php foreach ($stages as $stage): ?>
            <li class="library-stage">
                <img class="library-stage__sprite pixelated" src="<?= $this->e($species->spriteUrlFor($stage)) ?>"
                     alt="<?= $this->e($species->name . ' as a ' . $stage) ?>" loading="lazy">
                <span class="library-stage__name"><?= $this->e($stage) ?></span>
                <?php if ($species->blurbFor($stage) !== null && $species->blurbFor($stage) !== ''): ?>
                    <p class="library-stage__blurb"><?= $this->e($species->blurbFor($stage)) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<?php if ($forms !== []): ?>
    <section aria-labelledby="other-forms">
        <h3 id="other-forms" class="library-section"><?= $this->t('library-species.rarer-forms', 'Rarer forms') ?></h3>
        <p class="library-hint"><?= $this->t('library-species.now-and-then-a-arrives', 'Now and then a {specie_name} arrives looking a little different. Keep an eye out.', ['specie_name' => $species->name]) ?></p>
        <?php foreach ($forms as $form): ?>
            <?php $dressed = $species->wearing($form); ?>
            <div class="library-form card">
                <h4 class="library-form__name"><?= $this->e($form->name) ?></h4>
                <?php if ($form->blurb !== null && $form->blurb !== ''): ?>
                    <p><?= $this->e($form->blurb) ?></p>
                <?php endif; ?>
                <ol class="library-form__sprites">
                    <?php foreach ($stages as $stage): ?>
                        <li>
                            <img class="pixelated" src="<?= $this->e($dressed->spriteUrlFor($stage)) ?>"
                                 alt="<?= $this->e($form->name . ' as a ' . $stage) ?>" loading="lazy">
                            <span><?= $this->e($stage) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section aria-labelledby="the-book">
    <h3 id="the-book" class="library-section"><?= $this->t('library-species.the-book', 'The book') ?></h3>
    <?php if ($loreParagraphs === []): ?>
        <p class="empty-state"><?= $this->t('library-species.this-book-hasn-t-been', 'This book hasn\'t been written yet. Its pages are waiting.') ?></p>
    <?php else: ?>
        <details class="book">
            <summary class="book__cover">
                <img class="book__cover-art pixelated" src="<?= $this->e($coverUrl) ?>" alt="" aria-hidden="true">
                <span class="book__cover-title"><?= $this->t('library-species.the-story-of-the', 'The Story of the {specie_name}', ['specie_name' => $species->name]) ?></span>
                <span class="book__cover-hint book__cover-hint--closed"><?= $this->t('library-species.tap-to-open-the-book', 'tap to open the book') ?></span>
                <span class="book__cover-hint book__cover-hint--open"><?= $this->t('library-species.tap-to-close-the-book', 'tap to close the book') ?></span>
            </summary>
            <div class="book__pages">
                <?php foreach ($loreParagraphs as $paragraph): ?>
                    <p><?= $this->e($paragraph) ?></p>
                <?php endforeach; ?>
                <p class="book__end" aria-hidden="true">&#10086;</p>
            </div>
        </details>
    <?php endif; ?>
</section>
