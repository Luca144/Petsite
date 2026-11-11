<?php
/**
 * The share box (M6.3): ready-made code for showing this creature off on a
 * forum or another site, plus a plain link.
 *
 * @package Felkyo\Templates
 *
 * Inserted by pages/creature.php. The site writes the markup; players never
 * do. The only player-chosen words in it are the creature's NAME, and it
 * goes through $this->e() twice over: once when building the HTML snippet
 * (so it is safe inside the snippet), and again when printing the snippet
 * into the textarea (so it is safe on THIS page). Two contexts, two escapes.
 *
 * Variables: $creature (Creature), $imagePath (string, site-relative),
 * $siteUrl (string, e.g. https://felkyo.example — no trailing slash).
 */
$pageUrl = $siteUrl . '/creature/' . $creature->id;
$imageUrl = str_starts_with($imagePath, 'http') ? $imagePath : $siteUrl . $imagePath;
$snippet = '<a href="' . $this->e($pageUrl) . '"><img src="' . $this->e($imageUrl) . '" alt="'
    . $this->e($creature->name . ' on Felkyo Creatures') . '"></a>';
$id = (string) $creature->id;
?>
<details class="creature-share">
    <summary><?= $this->t('creature-share.share-on-other-sites', 'Share {name} on other sites', ['name' => $creature->name]) ?></summary>
    <p class="field__hint"><?= $this->t('creature-share.copy-one-of-these-and', 'Copy one of these and paste it where you\'d like {name} to appear. Visitors can click through to say hello.', ['name' => $creature->name]) ?></p>
    <div class="field">
        <label class="field__label" for="share-html-<?= $this->e($id) ?>"><?= $this->t('creature-share.picture-with-a-link-html', 'Picture with a link (HTML, for forums and websites)') ?></label>
        <textarea class="field__input creature-share__code" id="share-html-<?= $this->e($id) ?>" rows="3" readonly><?= $this->e($snippet) ?></textarea>
    </div>
    <div class="field">
        <label class="field__label" for="share-link-<?= $this->e($id) ?>"><?= $this->t('creature-share.just-the-link', 'Just the link') ?></label>
        <input class="field__input creature-share__code" id="share-link-<?= $this->e($id) ?>" type="text" readonly value="<?= $this->e($pageUrl) ?>">
    </div>
</details>
