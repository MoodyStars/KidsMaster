<?php
// _includes/header.php
require_once __DIR__ . '/init.php';
$currentUser = km_current_user();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= isset($page_title) ? km_esc($page_title).' — KidsMaster' : 'KidsMaster' ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="/assets/css/channel_1_5_deluxe.css">
  <script>window.KM_USER = <?= json_encode($currentUser ?? null) ?>; window.KM_CSRF = <?= json_encode(km_csrf_token()) ?>;</script>
  <script src="/assets/js/compat.js" defer></script>
  <script src="/assets/js/channel_actions.js" defer></script>
</head>
<body class="km-body">
<header class="site-header">
  <div class="brand">
    <a href="/"><strong>KidsMaster</strong></a>
    <span class="tag">Share your Memes & Nostalgia</span>
  </div>
  <nav class="main-nav">
    <a href="/index.php">Home</a>
    <a href="/browse.php">Browse</a>
    <a href="/channels.php">Channels</a>
    <a href="/upload_ui.php">Upload</a>
    <a href="/community.php">Community</a>
    <a href="/help.php">Help</a>
  </nav>
  <div class="user-nav">
    <?php if ($currentUser): ?>
      <a href="/my_videos.php"><?= km_esc($currentUser['username']) ?></a>
      <a href="/logout.php">Logout</a>
    <?php else: ?>
      <a href="/login.php">Log In</a>
      <a href="/register.php">Sign Up</a>
    <?php endif; ?>
  </div>
</header>
<main class="site-main">