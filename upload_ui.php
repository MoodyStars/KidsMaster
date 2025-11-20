<?php
// upload_ui.php - improved upload UI that pre-selects channel, supports resumable chunked uploads via upload_widget.js
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/api.php';
require_login();
$user = current_user();
$channels = [];
$pdo = db();
$stmt = $pdo->prepare("SELECT id,name FROM channels WHERE owner_id = :uid");
$stmt->execute([':uid'=>$user['id']]);
$channels = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Upload — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/site_extended.css" />
  <script>window.KM_USER = <?=json_encode($user)?>; window.KM_CSRF = <?=json_encode(csrf_token())?>;</script>
  <script defer src="assets/js/upload_widget.js"></script>
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
  <main class="container">
    <h1>Upload New Media</h1>
    <section class="panel">
      <form id="uploadForm" onsubmit="return false;">
        <?= csrf_field_html() ?>
        <label>Channel
          <select id="uploadChannel" name="channel_id">
            <?php foreach ($channels as $c): ?>
              <option value="<?=htmlspecialchars($c['id'])?>"><?=htmlspecialchars($c['name'])?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Title<br><input id="uploadTitle" name="title" required></label>
        <label>Description<br><textarea id="uploadDesc" name="description"></textarea></label>
        <label>Tags (comma separated)<br><input id="uploadTags" name="tags"></label>

        <label>Choose file<br><input id="uploadFile" type="file" name="file" required></label>

        <div id="uploadProgress" class="upload-progress" style="display:none;">
          <div class="bar"><div class="bar-fill" style="width:0%"></div></div>
          <div class="progress-meta"><span id="progressText">0%</span></div>
        </div>

        <div style="margin-top:12px;">
          <button class="btn" id="startUpload">Start Upload</button>
          <button class="btn ghost" id="cancelUpload">Cancel</button>
        </div>
      </form>
    </section>
    <section class="panel">
      <h3>Upload Tips</h3>
      <ul>
        <li>Supports common formats (mp4, webm, mp3, jpg, png, zip for software)</li>
        <li>Chunked uploads allow resume on unstable connections.</li>
        <li>Per-user storage quota sample: 512GB (configurable on server).</li>
      </ul>
    </section>
  </main>
</body>
</html>