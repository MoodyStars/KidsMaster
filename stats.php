<?php
// stats.php - user statistics, storage usage and most viewed videos
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';
require_login();
$user = current_user();
$pdo = db();

// storage usage
$usage = api_get_user_storage_usage($user['id']);

// user uploads count and total views across their media
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(views),0) as views FROM media m JOIN channels c ON m.channel_id = c.id WHERE c.owner_id = :uid");
$stmt->execute([':uid'=>$user['id']]);
$row = $stmt->fetch();

// most viewed by user
$stmt2 = $pdo->prepare("SELECT id,title,views,thumbnail FROM media m JOIN channels c ON m.channel_id = c.id WHERE c.owner_id = :uid ORDER BY views DESC LIMIT 8");
$stmt2->execute([':uid'=>$user['id']]);
$top = $stmt2->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Statistics — KidsMaster</title><link rel="stylesheet" href="assets/css/style.css" /></head><body class="km-body">
<header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
<main class="container">
  <h1>Your Stats</h1>
  <div class="panel">
    <p>Storage usage: <?= number_format($usage / 1024 / 1024, 2) ?> MB (quota sample: 512GB)</p>
    <p>Uploads: <?=htmlspecialchars($row['cnt'])?> • Total views: <?=htmlspecialchars($row['views'])?></p>
  </div>
  <div class="panel">
    <h3>Your Most Viewed</h3>
    <div class="thumb-grid small">
      <?php foreach ($top as $t): ?>
        <article class="thumb small"><a href="watch.php?id=<?=htmlspecialchars($t['id'])?>"><img src="<?=htmlspecialchars($t['thumbnail'])?>"><div class="thumb-meta"><h4><?=htmlspecialchars($t['title'])?></h4><small><?=htmlspecialchars($t['views'])?> views</small></div></a></article>
      <?php endforeach; ?>
    </div>
  </div>
</main></body></html>