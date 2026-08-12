<?php
/**
 * The item category icons, as one small inline SVG sprite.
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: eight little drawings, defined once per page and then referenced
 * wherever a category is shown. Insert it ONCE near the top of any page that
 * renders item cards:  $this->insert('partials/item-icons');
 *
 * WHY A SPRITE AND NOT AN ICON LIBRARY: adding a library would mean a new
 * dependency and a download for every visitor, to draw eight shapes. CLAUDE.md
 * asks us to write the small amount of code ourselves rather than pull a package,
 * and this is exactly that case. Everything here is plain SVG — no build step, no
 * font, nothing to keep updated.
 *
 * WHY THE ICONS MATTER: they are the second of the three signals a category
 * carries (tint, icon, word). Roughly one man in twelve cannot separate the
 * greens from the reds, so a card that leaned on colour alone would simply lose
 * them. The icon is recognisable without reading, and the word never fails
 * anybody at all.
 *
 * They are drawn with "currentColor", so they take the colour of the text beside
 * them and stay readable inside any category tint and in any theme added later.
 *
 * TO ADD ONE: copy a <symbol>, give it a new id of the form "icon-{key}", and use
 * that key as a category's icon_key. Keep it inside the 24x24 box.
 */
?>
<svg class="icon-sprite" aria-hidden="true" focusable="false" width="0" height="0" style="position:absolute">
    <defs>
        <!-- Ingredient: a leaf, for things grown and gathered. -->
        <symbol id="icon-leaf" viewBox="0 0 24 24">
            <path d="M5 19c0-8 5-13 14-14 1 10-4 15-11 15H5z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M5 19C8 15 12 12 17 9.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </symbol>

        <!-- Dish: a bowl with a curl of steam, for cooked food. -->
        <symbol id="icon-bowl" viewBox="0 0 24 24">
            <path d="M3.5 12h17c0 4.5-3.8 7.5-8.5 7.5S3.5 16.5 3.5 12z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M12 8.5c1.6-1.2.4-2.6 0-3.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </symbol>

        <!-- Potion: a round flask, for brewed things. -->
        <symbol id="icon-flask" viewBox="0 0 24 24">
            <path d="M10 3.5h4M11 4v4.5l-4 6.2A3.6 3.6 0 0 0 10 20h4a3.6 3.6 0 0 0 3-5.3L13 8.5V4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round"/>
            <circle cx="12" cy="16" r="1.2" fill="currentColor"/>
        </symbol>

        <!-- Material: a cut gem, for claws, ore and feathers. -->
        <symbol id="icon-gem" viewBox="0 0 24 24">
            <path d="M7 4h10l4 5.5-9 10.5L3 9.5 7 4z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="M3 9.5h18M12 20 8.5 9.5 11 4M12 20l3.5-10.5L13 4" fill="none" stroke="currentColor" stroke-width="1.2"/>
        </symbol>

        <!-- Seed: a sprout breaking ground, for things you plant. -->
        <symbol id="icon-sprout" viewBox="0 0 24 24">
            <path d="M12 20v-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
            <path d="M12 13C12 9.5 9.5 7.5 6 7.5c0 3.5 2.5 5.5 6 5.5zM12 13c0-3 2-4.8 5-4.8 0 3-2 4.8-5 4.8z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        </symbol>

        <!-- Tool: a hammer, for the things that unlock an activity. -->
        <symbol id="icon-tool" viewBox="0 0 24 24">
            <path d="M13.5 4.5 19 10l-2.5 2.5L11 7l2.5-2.5z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="m12 8-7 7a2 2 0 0 0 2.8 2.8l7-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
        </symbol>

        <!-- Sticker: a star, for decoration. -->
        <symbol id="icon-star" viewBox="0 0 24 24">
            <path d="m12 3.5 2.6 5.6 6 .8-4.4 4.2 1.1 6-5.3-2.9-5.3 2.9 1.1-6L3.4 9.9l6-.8L12 3.5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
        </symbol>

        <!-- Badge: a shield, for markers that were earned. -->
        <symbol id="icon-shield" viewBox="0 0 24 24">
            <path d="M12 3.5 19 6v6c0 4-3 6.8-7 8.5C8 18.8 5 16 5 12V6l7-2.5z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
            <path d="m9 12 2.2 2.2L15.5 10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </symbol>
    </defs>
</svg>
