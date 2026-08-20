/*
 * sidebar-fold.js — remembers whether you folded your sidebar away.
 *
 * WHAT THIS IS: one cookie write per toggle. The sidebar panel is a native
 * <details>, so opening and closing works entirely without this file — what this
 * adds is MEMORY: the cookie is read by the SERVER (public/index.php), which
 * renders the panel already-open or already-closed on the next page. That is what
 * stops it springing open on every navigation only to be snapped shut again —
 * the one failure mode the Product Owner named when asking for the fold.
 *
 * Server-side rendering of the state (rather than JS re-folding it on load) also
 * means no flash of the wrong state: the HTML arrives correct.
 *
 * The cookie is a pure UI preference. The server only ever compares it against
 * the string "closed" — it is never trusted as data.
 */
(function () {
    'use strict';

    var fold = document.querySelector('.site-side__fold');
    if (!fold) {
        return; // Logged out: there is no sidebar to remember.
    }

    fold.addEventListener('toggle', function () {
        // A year, renewed on every toggle. SameSite=Lax because nothing about a
        // fold preference should ever travel on a cross-site request.
        document.cookie = 'felkyo_sidebar=' + (fold.open ? 'open' : 'closed')
            + '; path=/; max-age=31536000; samesite=lax';
    });
})();
