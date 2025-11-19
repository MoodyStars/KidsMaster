<?php
session_start();
require_once __DIR__ . '/api.php';
$id = $_GET['id'] ?? null;
if (!$id) { header('Location: index.php'); exit; }

$media = api_get_media($id);
$related = api_list_media(['type' => $media['type'] ?? 'video', 'limit' => 6, 'category' => $media['category'] ?? null]);
$comments = api_get_comments($id, 0, 50);

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title><?=htmlspecialchars($media['title'] ?? 'Play')?> — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <script defer src="assets/js/main.js"></script>
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>

  <main class="watch-page">
    <div class="player-col">
      <?php if (($media['type'] ?? '') === 'video'): ?>
        <video id="player" controls poster="<?=htmlspecialchars($media['thumbnail'])?>" width="800">
          <source src="<?=htmlspecialchars($media['file_url'])?>" type="<?=htmlspecialchars($media['mime'] ?? 'video/mp4')?>">
          Your browser does not support HTML5 video.
        </video>
      <?php elseif (($media['type'] ?? '') === 'audio'): ?>
        <audio controls src="<?=htmlspecialchars($media['file_url'])?>"></audio>
      <?php else: ?>
        <img src="<?=htmlspecialchars($media['file_url'])?>" alt="<?=htmlspecialchars($media['title'])?>" />
      <?php endif; ?>

      <h1><?=htmlspecialchars($media['title'])?></h1>
      <div class="meta">
        <strong><?=htmlspecialchars($media['views'])?></strong> views • uploaded by <a href="channel.php?id=<?=htmlspecialchars($media['channel_id'])?>"><?=htmlspecialchars($media['channel_name'])?></a>
      </div>

      <div class="actions">
        <button onclick="toggleLike(<?=json_encode($media['id'])?>)">Like</button>
        <button onclick="subscribe(<?=json_encode($media['channel_id'])?>)">Subscribe</button>
        <button onclick="openShare()">Share</button>
      </div>

      <section class="comments">
        <h3>Comments</h3>
        <?php if (isset($_SESSION['user'])): ?>
          <form onsubmit="postComment(event, <?=json_encode($media['id'])?>)">
            <textarea id="commentText" placeholder="Add a comment..." required></textarea>
            <div class="comment-controls">
              <button type="button" onclick="openEmojiPicker()">😊</button>
              <select id="countryFlag">
                <option value="us">🇺🇸</option>
                <option value="gb">🇬🇧</option>
                <option value="sa">🇸🇦</option>
                <option value="eg">🇪🇬</option>
                <option value="in">🇮🇳</option>
              </select>
              <button type="submit">Post Comment</button>
            </div>
          </form>
        <?php else: ?>
          <p><a href="login.php">Log in</a> to comment.</p>
        <?php endif; ?>

        <div id="commentsList">
          <?php foreach ($comments as $c): ?>
            <div class="comment">
              <div class="avatar"><?=htmlspecialchars(substr($c['author'],0,1))?></div>
              <div class="cbody">
                <div class="chead"><strong><?=htmlspecialchars($c['author'])?></strong> <small><?=htmlspecialchars($c['created_at'])?></small></div>
                <div class="ctext"><?=nl2br(htmlspecialchars($c['body']))?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <aside class="side-col">
      <div class="chat-panel">
        <h4>Live Chat</h4>
        <div id="chatWindow" class="chat-window"></div>
        <form id="chatForm" onsubmit="return false;">
          <input id="chatMsg" placeholder="Send a message..." />
          <select id="chatEmojiPicker"><option>😊</option><option>👍</option><option>🔥</option></select>
          <button onclick="sendChat(<?=json_encode($media['id'])?>)">Send</button>
        </form>
      </div>

      <div class="panel">
        <h4>Related</h4>
        <ul class="related">
          <?php foreach($related as $r): ?>
            <li><a href="watch.php?id=<?=htmlspecialchars($r['id'])?>"><?=htmlspecialchars($r['title'])?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </main>
</body>
</html>