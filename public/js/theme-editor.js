/*
 * theme-editor.js — live readability checking in the panel's theme editor
 * (M4.2).
 *
 * WHAT IT DOES: whenever a colour picker changes, it (1) repaints the preview
 * card by setting that colour token on the preview element, and (2) re-runs
 * every readability rule and rewrites the "How it reads" list. So the artist
 * sees a problem the moment she picks the colour, instead of after saving.
 *
 * WHERE THE RULES COME FROM: the form's data-rules attribute, written by the
 * server from ThemeTokens::RULES. This file has no rules of its own, so it
 * can never disagree with the server — and the server checks again anyway
 * and is the one that decides (this is a convenience, not a gate).
 *
 * The contrast maths is the WCAG 2 formula, the same one ColourContrast.php
 * uses: relative luminance of each colour, then (lighter + 0.05) / (darker + 0.05).
 */
(function () {
    'use strict';

    var form = document.querySelector('.theme-editor');
    if (!form) {
        return;
    }

    var rules = JSON.parse(form.getAttribute('data-rules') || '[]');
    var minimum = parseFloat(form.getAttribute('data-minimum') || '4.5');
    var pickers = form.querySelectorAll('input[type="color"][data-token]');
    var preview = form.querySelector('.theme-editor__preview');
    var list = form.querySelector('.theme-editor__checks');

    // One channel (0–255) → its linear-light value, per the WCAG definition.
    function channel(value) {
        var c = value / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    }

    function luminance(hex) {
        var r = parseInt(hex.substr(1, 2), 16);
        var g = parseInt(hex.substr(3, 2), 16);
        var b = parseInt(hex.substr(5, 2), 16);
        return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
    }

    function ratio(a, b) {
        var la = luminance(a);
        var lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    // The current palette, read straight from the pickers.
    function palette() {
        var colours = {};
        pickers.forEach(function (picker) {
            colours[picker.getAttribute('data-token')] = picker.value;
        });
        return colours;
    }

    // Rebuild the readability list. Each line says pass or fail in WORDS and
    // a symbol, never by colour alone.
    function check() {
        var colours = palette();
        var problems = [];
        rules.forEach(function (rule) {
            var r = ratio(colours[rule.text], colours[rule.background]);
            if (r < minimum) {
                var where = rule.where.charAt(0).toUpperCase() + rule.where.slice(1);
                problems.push(where + ' is hard to read (' + r.toFixed(1) + ':1 — needs ' + minimum.toFixed(1) + ':1).');
            }
        });

        list.textContent = '';
        if (problems.length === 0) {
            addLine('theme-editor__ok', '✓ Readable everywhere.');
        } else {
            problems.forEach(function (problem) {
                addLine('theme-editor__bad', '✗ ' + problem);
            });
        }
    }

    function addLine(className, text) {
        var item = document.createElement('li');
        item.className = className;
        item.textContent = text;
        list.appendChild(item);
    }

    pickers.forEach(function (picker) {
        picker.addEventListener('input', function () {
            preview.style.setProperty('--' + picker.getAttribute('data-token'), picker.value);
            check();
        });
    });
}());
