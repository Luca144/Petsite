<?php
/**
 * Base page layout (skeleton version).
 *
 * @package Felkyo\Templates
 *
 * WHAT THIS IS: the outer HTML wrapper that every page shares — the <html>,
 * <head> and <body> shell. Individual page templates fill in the "content"
 * section (see below) and set a title.
 *
 * IMPORTANT: this is a deliberately bare skeleton. The real, on-brand themed
 * layout — colours, fonts, header and footer, the cosy autumn look — is built in
 * increment 0.3. For now this exists only so the "hello" route can render
 * through the full stack. Do not add styling here; that belongs in 0.3.
 *
 * Plates note: values passed to render() are available as PHP variables here,
 * and Plates escapes them for us when we use $this->e(...), which prevents
 * user-provided text from breaking the page or injecting scripts.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <!-- Mobile-first: this tells phones to use their real width, not pretend to
         be a desktop. The site is designed for a ~360px phone screen first. -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title) ?></title>
</head>
<body>
    <?= $this->section('content') ?>
</body>
</html>
