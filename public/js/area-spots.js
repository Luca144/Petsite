/**
 * area-spots.js — click the scene to place a search spot (M2.5).
 *
 * PURE ENHANCEMENT over the area editor's number fields, which are the real
 * form and work completely without this file: keyboard users and no-JS
 * browsers type percentages; this layer lets everyone else point instead.
 * (The standing rule from the decisions log: placing works by tapping AND
 * by keyboard — clicking is never the only way.)
 *
 * HOW IT WORKS, whole story: the scene box ([data-spot-scene]) is the
 * canvas. On load, a marker is drawn for every filled x/y pair in the
 * fieldset ([data-spot-rows]). Clicking the scene converts the click to
 * percentages and writes them into the first blank pair (adding a fresh
 * pair when none is blank, up to the server's cap of 20). Clicking a
 * marker clears its pair. Every change re-renders from the FIELDS — the
 * fields are the single source of truth, so what you save is exactly what
 * you see, and the server re-validates everything regardless.
 */
(function () {
    'use strict';

    var scene = document.querySelector('[data-spot-scene]');
    var rowsBox = document.querySelector('[data-spot-rows]');
    var hint = document.querySelector('[data-spot-hint]');
    if (!scene || !rowsBox) {
        return;
    }

    var MAX_SPOTS = 20;

    // The hint only speaks when the enhancement is actually running.
    if (hint) {
        hint.hidden = false;
    }
    scene.classList.add('admin-scene--clickable');

    /** Every x/y input pair, in order. */
    function pairs() {
        var xs = rowsBox.querySelectorAll('input[name="spot_x[]"]');
        var ys = rowsBox.querySelectorAll('input[name="spot_y[]"]');
        var list = [];
        for (var i = 0; i < xs.length; i++) {
            list.push({ x: xs[i], y: ys[i] });
        }
        return list;
    }

    /** Redraw all markers from the fields (the source of truth). */
    function render() {
        scene.querySelectorAll('.admin-scene__marker').forEach(function (marker) {
            marker.remove();
        });

        pairs().forEach(function (pair, index) {
            if (pair.x.value === '' || pair.y.value === '') {
                return;
            }
            var marker = document.createElement('button');
            marker.type = 'button'; // never submits the form
            marker.className = 'admin-scene__marker';
            marker.style.setProperty('--x', pair.x.value + '%');
            marker.style.setProperty('--y', pair.y.value + '%');
            marker.title = 'Remove this spot (' + pair.x.value + ', ' + pair.y.value + ')';
            marker.setAttribute('aria-hidden', 'true'); // the fields are the accessible truth
            marker.tabIndex = -1;
            marker.textContent = '✦';
            marker.addEventListener('click', function (event) {
                event.preventDefault();
                pair.x.value = '';
                pair.y.value = '';
                render();
            });
            scene.appendChild(marker);
        });
    }

    /** A fresh blank row, cloned from the first one's structure. */
    function addBlankPair() {
        var firstRow = rowsBox.querySelector('.admin-scene__row');
        if (!firstRow) {
            return null;
        }
        var row = firstRow.cloneNode(true);
        row.querySelectorAll('input').forEach(function (input) {
            input.value = '';
        });
        rowsBox.appendChild(row);
        var all = pairs();
        return all[all.length - 1];
    }

    scene.addEventListener('click', function (event) {
        var box = scene.getBoundingClientRect();
        // The click as whole percentages across the scene, clamped 0–100.
        var x = Math.round(((event.clientX - box.left) / box.width) * 100);
        var y = Math.round(((event.clientY - box.top) / box.height) * 100);
        x = Math.max(0, Math.min(100, x));
        y = Math.max(0, Math.min(100, y));

        var target = null;
        var filled = 0;
        pairs().forEach(function (pair) {
            if (pair.x.value !== '' && pair.y.value !== '') {
                filled++;
            } else if (!target) {
                target = pair;
            }
        });
        if (filled >= MAX_SPOTS) {
            return; // the server's cap; the form says so on save anyway
        }
        if (!target) {
            target = addBlankPair();
        }
        if (!target) {
            return;
        }

        target.x.value = String(x);
        target.y.value = String(y);
        render();
    });

    // Typing in the numbers moves the markers too — the fields lead.
    rowsBox.addEventListener('input', render);

    render();
})();
