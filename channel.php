<?php
// channel.php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';

$channel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$channel_id) { header('Location: index.php'); exit; }

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM channels WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$channel_id]);
$channel = $stmt->fetch();
if (!$channel) { http_response_code(404); echo "Channel not found"; exit; }

$mediaList = api_list_media(['limit'=>24,'page'=>1,'channel_id'=>$channel_id]);
$currentUser = current_user();
$is_subscribed = false;
if ($currentUser) {
  $s = $pdo->prepare("SELECT 1 FROM subscriptions WHERE user_id = :u AND channel_id = :c LIMIT 1");
  $s->execute([':u'=>$currentUser['id'], ':c'=>$channel_id]);
  $is_subscribed = (bool)$s->fetch();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title><?=htmlspecialchars($channel['name'])?> — KidsMaster Channel</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/channel.css" />
  <script>window.KM_USER = <?=json_encode($currentUser ?? null)?>; window.KM_CSRF = <?=json_encode(csrf_token())?>;</script>
  <meta name="channel-id" content="<?=htmlspecialchars($channel_id)?>">
  <script defer src="assets/js/channel.js"></script>
</head>
<body class="km-body">
  <header class="km-header">
    <div class="km-brand"><a href="index.php">KidsMaster</a></div>
    <nav class="km-nav">
      <a href="index.php">Home</a> | <a href="upload.php">Upload</a>
      <?php if ($currentUser): ?>
        <a href="channel_edit.php?id=<?=htmlspecialchars($channel_id)?>">Edit Channel</a>
      <?php endif; ?>
    </nav>
  </header>

  <main class="channel-page">
    <section class="channel-banner">
      <?php if (!empty($channel['gif_banner'])): ?>
        <img src="<?=htmlspecialchars($channel['gif_banner'])?>" alt="Banner" class="banner-gif">
      <?php elseif (!empty($channel['banner'])): ?>
        <img src="<?=htmlspecialchars($channel['banner'])?>" alt="Banner" class="banner">
      <?php else: ?>
        <div class="banner placeholder">No banner</div>
      <?php endif; ?>
      <div class="channel-meta">
        <div class="profile-pic">
          <?php if (!empty($channel['profile_pic'])): ?>
            <img src="<?=htmlspecialchars($channel['profile_pic'])?>" alt="Avatar">
          <?php else: ?>
            <div class="avatar-placeholder"><?=htmlspecialchars(substr($channel['name'],0,1))?></div>
          <?php endif; ?>
        </div>
        <div class="info">
          <h1><?=htmlspecialchars($channel['name'])?></h1>
          <p class="desc"><?=nl2br(htmlspecialchars($channel['description'] ?? ''))?></p>
          <div class="channel-controls">
            <button id="subscribeBtn" data-subs="<?= $is_subscribed?1:0 ?>" data-channel="<?=htmlspecialchars($channel_id)?>">
              <?= $is_subscribed ? 'Subscribed' : 'Subscribe' ?>
            </button>
            <a class="btn" href="upload.php?channel_id=<?=htmlspecialchars($channel_id)?>">New Video</a>
            <a class="btn ghost" href="#" id="liveBtn">Start Live (stub)</a>
          </div>
        </div>
      </div>
    </section>

    <section class="channel-body">
      <div class="left-col">
        <div class="panel">
          <h3>Videos by <?=htmlspecialchars($channel['name'])?></h3>
          <div class="thumb-grid">
            <?php foreach ($mediaList as $m): ?>
              <article class="thumb">
                <a href="watch.php?id=<?=htmlspecialchars($m['id'])?>">
                  <img src="<?=htmlspecialchars($m['thumbnail'])?>" alt="<?=htmlspecialchars($m['title'])?>">
                  <div class="thumb-meta"><h4><?=htmlspecialchars($m['title'])?></h4></div>
                </a>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <aside class="right-col">
        <div class="panel chat-panel">
          <h4>Channel Chat (SMS style)</h4>
          <div id="channelChatWindow" class="chat-window"></div>

          <div class="chat-controls">
            <select id="chatCountryFlag">
              <option value="us">🇺🇸 US</option>
              <option value="gb">🇬🇧 UK</option>
              <option value="in">🇮🇳 IN</option>
              <option value="eg">🇪🇬 EG</option>
            </select>
            <button onclick="openEmojiPicker()">😊</button>
            <input id="channelChatMsg" placeholder="Send a message..." />
            <button id="channelSendBtn">Send</button>
          </div>

          <div class="chat-extra">
            <a href="#" id="redditChatLink">Open Reddit-style chat (stub)</a>
          </div>
        </div>

        <div class="panel">
          <h4>About</h4>
          <p>Subscribers: <strong><?=htmlspecialchars($channel['subscribers'] ?? 0)?></strong></p>
          <p>Created: <?=htmlspecialchars($channel['created_at'])?></p>
        </div>
      </aside>
    </section>

    <footer class="km-footer">
      <div class="copyright">Copyright © 2025 KidsMaster All rights reserved.</div>
    </footer>
  </main>
</body>
</html>