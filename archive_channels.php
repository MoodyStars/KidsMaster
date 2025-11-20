<?php
// archive_channels.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';
require_login();
$user = current_user();
$pdo = db();

// Only moderators or owners to see all archived channels; normal users see their own archived channels.
$is_mod = (int)($user['is_moderator'] ?? 0);
if ($is_mod) {
    $stmt = $pdo->prepare("SELECT * FROM channels WHERE archived = 1 ORDER BY created_at DESC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM channels WHERE archived = 1 AND owner_id = :uid ORDER BY created_at DESC");
    $stmt->execute([':uid'=>$user['id']]);
}
$channels = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Archived Channels — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/channel.css" />
  <script>window.KM_USER = <?=json_encode($user)?>; window.KM_CSRF = <?=json_encode(csrf_token())?>;</script>
  <script defer src="assets/js/archive.js"></script>
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
  <main class="container">
    <h1>Archived Channels</h1>
    <div class="panel">
      <?php if (!$channels): ?>
        <p>No archived channels found.</p>
      <?php else: ?>
        <ul class="archived-list">
          <?php foreach ($channels as $c): ?>
            <li class="archived-item">
              <div class="archived-meta">
                <strong><?=htmlspecialchars($c['name'])?></strong>
                <div><?=htmlspecialchars($c['created_at'])?> • Owner ID: <?=htmlspecialchars($c['owner_id'])?></div>
              </div>
              <div class="archived-actions">
                <?php if ($c['owner_id'] == $user['id'] || $is_mod): ?>
                  <form method="post" action="/ajax/channel_api.php?action=restore_channel" onsubmit="return restoreChannel(this);">
                    <?= csrf_field_html() ?>
                    <input type="hidden" name="channel_id" value="<?=htmlspecialchars($c['id'])?>">
                    <button class="btn">Restore</button>
                  </form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>