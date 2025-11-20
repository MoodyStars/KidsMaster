<?php
// events.php - Events & Contests listing and submission form for uploads tagged to a contest
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';
$currentUser = current_user();

$pdo = db();
// simple events table assumed; if not present, show static content
$events = $pdo->query("SELECT * FROM events ORDER BY starts_at DESC LIMIT 20")->fetchAll() ?: [];
?>
<!doctype html><html><head><meta charset="utf-8"><title>Events & Contests</title><link rel="stylesheet" href="assets/css/style.css" /></head><body class="km-body">
<header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
<main class="container">
  <h1>Events & Contests</h1>
  <section class="panel">
    <?php if (!$events): ?>
      <p>No active events. Example: Retro Remix (upload 2000s clips) — Keep an eye here for contests.</p>
    <?php else: ?>
      <?php foreach ($events as $e): ?>
        <div class="event">
          <h3><?=htmlspecialchars($e['title'])?></h3>
          <p><?=nl2br(htmlspecialchars($e['description']))?></p>
          <p><small>Starts: <?=htmlspecialchars($e['starts_at'])?> • Ends: <?=htmlspecialchars($e['ends_at'])?></small></p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main></body></html>