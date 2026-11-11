/**
 * bug-report.js — tells a bug report how wide the player's window is.
 *
 * Most layout bugs only happen at some widths ("cut off on my phone"), so
 * knowing the width saves a lot of back-and-forth. This only fills in a
 * hidden field on the bug report form; without JavaScript the field stays
 * empty and the report still sends.
 */
(function () {
    'use strict';

    var field = document.querySelector('[data-screen-width]');
    if (field) {
        field.value = String(window.innerWidth);
    }
})();
