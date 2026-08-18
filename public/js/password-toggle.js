/*
 * password-toggle.js — toggle password visibility
 *
 * WHAT THIS IS: when the user checks "Show", switches the password input from
 * type="password" to type="text" so they can see what they typed.
 *
 * WHY IT'S VANILLA: this is enhancement, not a requirement. The login/register
 * forms work fine without it; the JavaScript just adds convenience.
 */
(function () {
    'use strict';

    // Find the show-password checkbox
    var checkbox = document.getElementById('show-password');
    if (!checkbox) {
        return; // Page doesn't have a password field
    }

    // Find the password input (should be right before the checkbox)
    var passwordInput = document.getElementById('password');
    if (!passwordInput) {
        return; // Shouldn't happen, but be safe
    }

    // Toggle the password visibility
    checkbox.addEventListener('change', function () {
        passwordInput.type = this.checked ? 'text' : 'password';
    });
})();
