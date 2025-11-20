<?php
// my_videos.php - user's videos list, with public/private toggle and delete
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';
require_login();
$user = current_user();
$pdo = db();
$stmt = $pdo->prepare("SELECT m.* FROM media m JOIN channels c ON m.channel_id = c.id WHERE c.owner_id = :uid ORDER BY m.created_at DESC");
$stmt->execute([':uid'=>$user['id']]);
$videos = $stmt->fetchAll();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>My Videos</title><link rel="stylesheet" href="assets/css/style.css" /></head><body class="km-body">
<header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
<main class="container">
  <h1>My Videos</h1>
  <div class="panel">
    <?php if (!$videos) echo '<p>No uploads yet.</p>'; ?>
    <ul class="my-media-list">
      <?php foreach ($videos as $v): ?>
        <li>
          <img src="<?=htmlspecialchars($v['thumbnail'])?>" alt="" width="88">
          <a href="watch.php?id=<?=htmlspecialchars($v['id'])?>"><?=htmlspecialchars($v['title'])?></a>
          <div class="meta"><?=htmlspecialchars($v['views'])?> views • <?=htmlspecialchars($v['created_at'])?></div>
          <div class="ops">
            <form method="post" action="ajax/media_api.php?action=toggle_priv" style="display:inline;">
              <?= csrf_field_html() ?><input type="hidden" name="media_id" value="<?=htmlspecialchars($v['id'])?>">
              <button class="btn"><?= $v['privacy']=='private' ? 'Make Public' : 'Make Private' ?></button>
            </form>
            <form method="post" action="ajax/media_api.php?action=delete" style="display:inline;" onsubmit="return confirm('Delete?')">
              <?= csrf_field_html() ?><input type="hidden" name="media_id" value="<?=htmlspecialchars($v['id'])?>">
              <button class="btn ghost">Delete</button>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</main></body></html>