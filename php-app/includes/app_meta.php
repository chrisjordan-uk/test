<?php
/**
 * Home-screen / "installable app" tags, included in the <head> of every
 * page (both includes/header.php and login.php). Lets a phone's browser
 * add this site to the home screen as a full-screen app with its own
 * icon — no App Store, no native build, just these tags + the manifest.
 */
?>
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#4b63f6">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/icon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/icons/icon-16.png">

<!-- iOS: makes "Add to Home Screen" open full-screen, without Safari's UI -->
<link rel="apple-touch-icon" href="assets/icons/icon-180.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Vinted Resell">
