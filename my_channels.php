<?php
// my_channels.php - list user's channels and quick edit link
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';
require_login();
$user = current_user();
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM channels WHERE owner_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid'=>$user['id']]);
$channels = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>My Channels</title><link rel="stylesheet" href="assets/css/style.css" /></head><body class="km-body">
<header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
<main class="container">
  <h1>My Channels</h1>
  <div class="panel">
    <?php if (!$channels) echo '<p>No channels yet.</p>'; ?>
    <ul>
      <?php foreach ($channels as $c): ?>
        <li>
          <strong><?=htmlspecialchars($c['name'])?></strong> • <?=htmlspecialchars($c['created_at'])?>
          <a class="btn" href="channel_edit.php?id=<?=htmlspecialchars($c['id'])?>">Edit</a>
          <a class="btn ghost" href="channel.php?id=<?=htmlspecialchars($c['id'])?>">View</a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</main></body></html>