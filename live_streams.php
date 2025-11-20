<?php
// live_streams.php
// Owner-only simple UI + API to create/manage a live stream for a channel.
// Integrates with api_ext.php for creation and status changes.

session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/api_ext.php';
require_login();
$user = current_user();

$channel_id = (int)($_GET['channel_id'] ?? ($_POST['channel_id'] ?? 0));
if (!$channel_id) { header('Location: index.php'); exit; }

// verify ownership
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM channels WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$channel_id]);
$ch = $stmt->fetch();
if (!$ch) { http_response_code(404); echo "Channel not found"; exit; }
if ($ch['owner_id'] != $user['id']) { http_response_code(403); echo "Not authorized"; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = $_POST['title'] ?? 'Live Stream';
        $desc = $_POST['description'] ?? '';
        $id = api_create_live_stream($channel_id, $title, $desc);
        header('Location: live_streams.php?channel_id=' . $channel_id . '&stream_id=' . $id);
        exit;
    } elseif ($action === 'start') {
        $stream_id = (int)$_POST['stream_id'];
        api_update_live_stream_status($stream_id, 'live');
        header('Location: live_streams.php?channel_id=' . $channel_id . '&stream_id=' . $stream_id);
        exit;
    } elseif ($action === 'end') {
        $stream_id = (int)$_POST['stream_id'];
        api_update_live_stream_status($stream_id, 'ended');
        header('Location: live_streams.php?channel_id=' . $channel_id . '&stream_id=' . $stream_id);
        exit;
    } elseif ($action === 'delete') {
        $stream_id = (int)$_POST['stream_id'];
        $pdo->prepare("DELETE FROM live_streams WHERE id = :id")->execute([':id'=>$stream_id]);
        header('Location: channel.php?id=' . $channel_id);
        exit;
    }
}

// GET: show streams for this channel
$streams = $pdo->prepare("SELECT * FROM live_streams WHERE channel_id = :cid ORDER BY created_at DESC");
$streams->execute([':cid'=>$channel_id]);
$rows = $streams->fetchAll();

$stream_id = (int)($_GET['stream_id'] ?? 0);
$currentStream = $stream_id ? api_get_live_stream($stream_id) : null;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Manage Live Streams</title>
<link rel="stylesheet" href="assets/css/style.css" />
</head>
<body class="km-body">
<header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
<main class="container">
  <h1>Live Streams — <?=htmlspecialchars($ch['name'])?></h1>

  <section class="panel">
    <h3>Create Stream</h3>
    <form method="post">
      <?= csrf_field_html() ?>
      <input type="hidden" name="channel_id" value="<?=htmlspecialchars($channel_id)?>">
      <input type="hidden" name="action" value="create">
      <label>Title<br><input name="title" required></label>
      <label>Description<br><textarea name="description"></textarea></label>
      <button class="btn" type="submit">Create</button>
    </form>
  </section>

  <section class="panel">
    <h3>Your Streams</h3>
    <?php foreach ($rows as $s): ?>
      <div style="padding:8px;border-bottom:1px solid #eee;">
        <strong><?=htmlspecialchars($s['title'])?></strong> — <?=htmlspecialchars($s['status'])?> <br>
        Created: <?=htmlspecialchars($s['created_at'])?><br>
        <?php if ($s['status'] === 'created'): ?>
          <form method="post" style="display:inline-block;">
            <?= csrf_field_html() ?>
            <input type="hidden" name="channel_id" value="<?=htmlspecialchars($channel_id)?>">
            <input type="hidden" name="stream_id" value="<?=htmlspecialchars($s['id'])?>">
            <input type="hidden" name="action" value="start">
            <button class="btn">Mark Live</button>
          </form>
        <?php elseif ($s['status'] === 'live'): ?>
          <form method="post" style="display:inline-block;">
            <?= csrf_field_html() ?>
            <input type="hidden" name="stream_id" value="<?=htmlspecialchars($s['id'])?>">
            <input type="hidden" name="action" value="end">
            <button class="btn ghost">End Stream</button>
          </form>
        <?php endif; ?>
        <form method="post" style="display:inline-block;margin-left:8px;">
          <?= csrf_field_html() ?>
          <input type="hidden" name="stream_id" value="<?=htmlspecialchars($s['id'])?>">
          <input type="hidden" name="action" value="delete">
          <button class="btn" onclick="return confirm('Delete stream?')">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($currentStream): ?>
    <section class="panel">
      <h3>Stream Preview</h3>
      <p>RTMP Key: <code><?=htmlspecialchars($currentStream['rtmp_key'])?></code> (use your encoder)</p>
      <p>HLS URL: <?=htmlspecialchars($currentStream['hls_url'] ?? 'Not generated')?></p>
      <p>Status: <?=htmlspecialchars($currentStream['status'])?></p>
    </section>
  <?php endif; ?>

</main>
</body>
</html>