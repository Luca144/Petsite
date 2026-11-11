/**
 * text-editor.js — editing mode: click (or press Enter on) any dotted
 * sentence to change it.
 *
 * Loaded only in editing mode, which only the artist and the owner can
 * switch on. Each editable sentence is a <span class="text-edit"> carrying
 * its key, its current words and its original (data attributes, set by the
 * t() template function). This script copies those into the one popup form
 * in the layout and opens it; saving is an ordinary form post that comes
 * back to this page — nothing is saved from here.
 *
 * WHY THE CLICK IS CAUGHT EARLY ("capture"). Many sentences sit inside a
 * link or a button. In editing mode, clicking the words should edit them,
 * not follow the link or press the button — so the click is stopped before
 * the link or button sees it. Switch editing mode off to use the site.
 */
(function () {
    'use strict';

    var dialog = document.getElementById('text-editor');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return; // A very old browser: the panel's text list still works.
    }
    var keyField = dialog.querySelector('[data-text-editor-key]');
    var wordsField = dialog.querySelector('#text-editor-words');
    var originalText = dialog.querySelector('[data-text-editor-original]');

    function open(sentence) {
        keyField.value = sentence.getAttribute('data-text-key');
        wordsField.value = sentence.getAttribute('data-text-words');
        originalText.textContent = sentence.getAttribute('data-text-original');
        dialog.showModal();
        wordsField.focus();
        wordsField.select();
    }

    document.addEventListener('click', function (event) {
        var sentence = event.target.closest ? event.target.closest('.text-edit') : null;
        if (!sentence || dialog.contains(sentence)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        open(sentence);
    }, true);

    // Keyboard: each sentence is focusable (tabindex="0"); Enter opens it.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && event.target.classList && event.target.classList.contains('text-edit')) {
            event.preventDefault();
            event.stopPropagation();
            open(event.target);
        }
    }, true);

    dialog.querySelector('[data-text-editor-close]').addEventListener('click', function () {
        dialog.close();
    });
})();
