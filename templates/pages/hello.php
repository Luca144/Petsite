<?php
/**
 * The "hello" page.
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: the temporary home page used to prove the whole stack works
 * (router -> template -> HTML). It will be replaced by real content in later
 * increments. It renders inside the shared layout.php wrapper.
 *
 * $appName is passed in from the route handler in public/index.php.
 */

// Tell Plates to wrap this page in layout.php, and pass the page title through.
$this->layout('layout', ['title' => 'Welcome to ' . $appName]);
?>

<main>
    <h1>Welcome to <?= $this->e($appName) ?></h1>
    <p>The foundations are in place. A cosy world of creatures is on its way.</p>
</main>
