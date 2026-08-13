/*
 * item-finder.js — makes the finder row filter without reloading the page.
 *
 * WHAT THIS IS: the site's first JavaScript, and it is an ENHANCEMENT, not a
 * requirement. The finder row (category pills + name search on the inventory
 * and shop pages) is plain links and a plain GET form, and those keep working
 * exactly as before when this file does not load. What this file adds: when
 * the full list is already sitting in the page, clicking a pill or typing in
 * the search box filters the cards right here in the browser — no request, no
 * reload, no flicker. Reloading mid-play is what made filtering feel like a
 * loading screen.
 *
 * HOW IT KNOWS IT MAY TAKE OVER: the server marks the finder with
 * data-complete-list ONLY when the page holds the whole unfiltered list. A
 * bookmarked filtered page (e.g. /inventory?category=dish) only contains the
 * matching cards, so filtering in the browser would have nothing to reveal —
 * there, this file stays out of the way and the links navigate normally.
 *
 * WHAT IT TOUCHES: every card carries data-category and data-name attributes
 * (written by the templates); sections of the grouped inventory view carry
 * data-category too. Filtering toggles the `hidden` attribute — the state a
 * screen reader also respects — and the visible "Showing X of Y" line is a
 * role="status" region, so the new count is announced after each change.
 *
 * THE URL IS KEPT HONEST: after each change the address bar is updated (no
 * navigation) to the same ?category=&q= shape the server understands, so a
 * filtered view can still be bookmarked or shared — whoever opens it gets the
 * server-rendered version of the same view.
 */
(function () {
    'use strict';

    var finder = document.querySelector('.finder');
    if (finder === null || !finder.hasAttribute('data-complete-list')) {
        return; // No finder here, or only a partial list: leave the links alone.
    }

    // Everything filterable on the page, and the section boxes of the grouped
    // inventory view (hidden whole when none of their cards survive).
    var cards = Array.prototype.slice.call(document.querySelectorAll('[data-category][data-name]'));
    var sections = Array.prototype.slice.call(document.querySelectorAll('[data-category-section]'));

    var pills = Array.prototype.slice.call(finder.querySelectorAll('.finder__pill'));
    var searchForm = finder.querySelector('.finder__search');
    var searchInput = finder.querySelector('.finder__input');
    var countLine = finder.querySelector('.finder__count');
    var countText = finder.querySelector('.finder__count-text');
    var resetLink = finder.querySelector('.finder__reset');
    var emptyLine = document.querySelector('.finder-empty');

    // The current filter state. Starts empty because data-complete-list is
    // only present on the unfiltered view.
    var activeCategory = '';
    var searchText = '';

    /*
     * Apply the current state to the page: show the matching cards, hide the
     * rest, hide emptied sections, refresh the count line, fix the address.
     */
    function applyFilters() {
        var shown = 0;
        var loweredSearch = searchText.toLowerCase();

        cards.forEach(function (card) {
            var matchesCategory = activeCategory === ''
                || card.getAttribute('data-category') === activeCategory;
            var matchesSearch = loweredSearch === ''
                || card.getAttribute('data-name').toLowerCase().indexOf(loweredSearch) !== -1;

            card.hidden = !(matchesCategory && matchesSearch);
            if (!card.hidden) {
                shown++;
            }
        });

        // A section heading with nothing under it is noise, not structure.
        sections.forEach(function (section) {
            var visibleCards = section.querySelectorAll('[data-category][data-name]:not([hidden])');
            section.hidden = visibleCards.length === 0;
        });

        // The active pill: aria-current carries the state (styling hangs off
        // it too, so the attribute and the look can never disagree).
        pills.forEach(function (pill) {
            var pillCategory = pill.getAttribute('data-category') || '';
            if (pillCategory === activeCategory) {
                pill.setAttribute('aria-current', 'true');
            } else {
                pill.removeAttribute('aria-current');
            }
        });

        // Say what happened — or fall quiet when nothing is filtered.
        var isFiltered = activeCategory !== '' || searchText !== '';
        countLine.hidden = !isFiltered;
        if (isFiltered) {
            countText.textContent = 'Showing ' + shown + ' of ' + cards.length
                + ' ' + finder.getAttribute('data-things-word') + '.';
        }
        if (emptyLine !== null) {
            emptyLine.hidden = !(isFiltered && shown === 0);
        }

        // Keep the address shareable without navigating. replaceState (rather
        // than pushState) on purpose: each keystroke is not a history entry a
        // player should have to back-button through.
        var query = [];
        if (activeCategory !== '') {
            query.push('category=' + encodeURIComponent(activeCategory));
        }
        if (searchText !== '') {
            query.push('q=' + encodeURIComponent(searchText));
        }
        var address = finder.getAttribute('data-action') + (query.length > 0 ? '?' + query.join('&') : '');
        window.history.replaceState(null, '', address);
    }

    // Pills switch the category in place instead of navigating.
    pills.forEach(function (pill) {
        pill.addEventListener('click', function (event) {
            event.preventDefault();
            activeCategory = pill.getAttribute('data-category') || '';
            applyFilters();
        });
    });

    // "Show everything" clears both filters in place.
    if (resetLink !== null) {
        resetLink.addEventListener('click', function (event) {
            event.preventDefault();
            activeCategory = '';
            searchText = '';
            if (searchInput !== null) {
                searchInput.value = '';
            }
            applyFilters();
        });
    }

    // The search filters as you type. A short pause is waited out first so
    // filtering happens between keystrokes, not on every single one.
    if (searchInput !== null) {
        var pendingSearch = null;

        searchInput.addEventListener('input', function () {
            window.clearTimeout(pendingSearch);
            pendingSearch = window.setTimeout(function () {
                // The same tidy-up the server does: trimmed, capped at 40.
                searchText = searchInput.value.trim().slice(0, 40);
                applyFilters();
            }, 120);
        });

        // Enter in the box would reload the page for a result that is already
        // on screen; with live filtering the submit has nothing left to do.
        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
        });
    }
})();
