<?php
// watch.php (updated): integrates live stream preview, reddit embed, threaded comments and analytics hooks.
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /'); exit; }
$pdo = km_db();
$media = $pdo->prepare("SELECT m.*, c.name as channel_name, c.id as channel_id FROM media m LEFT JOIN channels c ON m.channel_id = c.id WHERE m.id = :id LIMIT 1");
$media->execute([':id'=>$id]); $m = $media->fetch();
if (!$m) { echo '<div class="panel">Media not found</div>'; require_once __DIR__ . '/_includes/footer.php'; exit; }

// fetch live for channel
$live = $pdo->prepare("SELECT * FROM live_streams WHERE channel_id = :cid AND status = 'live' LIMIT 1");
$live->execute([':cid'=>$m['channel_id']]); $liveRow = $live->fetch();

// fetch reddit embed if configured
$red = $pdo->prepare("SELECT reddit_subreddit, reddit_thread_url FROM reddit_integrations WHERE channel_id = :cid ORDER BY id DESC LIMIT 1");
$red->execute([':cid'=>$m['channel_id']]); $redRow = $red->fetch();

// comments (threaded)
$comments = $pdo->prepare("SELECT * FROM comments WHERE media_id = :mid ORDER BY created_at ASC");
$comments->execute([':mid'=>$id]); $commentsList = $comments->fetchAll();

?>
<section class="panel">
  <div class="player-col">
    <?php if ($liveRow): ?>
      <div class="panel notice">
        <strong>Live Now:</strong> <?= km_esc($liveRow['title']) ?>
        <?php if (!empty($liveRow['hls_url'])): ?>
          <video controls autoplay src="<?= km_esc($liveRow['hls_url'])?>" style="width:100%"></video>
        <?php else: ?>
          <div>RTMP key: <code><?= km_esc($liveRow['rtmp_key']) ?></code> — configure your encoder.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($m['type'] === 'video'): ?>
      <video id="player" controls poster="<?= km_esc($m['thumbnail'])?>" style="width:100%"><source src="<?= km_esc($m['file_url'])?>" type="<?= km_esc($m['mime'] ?: 'video/mp4') ?>"></video>
    <?php elseif ($m['type'] === 'audio'): ?>
      <audio controls src="<?= km_esc($m['file_url']) ?>"></audio>
    <?php else: ?>
      <img src="<?= km_esc($m['file_url']) ?>" alt="<?= km_esc($m['title'])?>" style="max-width:100%;">
    <?php endif; ?>

    <h1><?= km_esc($m['title']) ?></h1>
    <div class="meta"><?= (int)$m['views'] ?> views • uploaded by <a href="/channel.php?id=<?= (int)$m['channel_id'] ?>"><?= km_esc($m['channel_name']) ?></a></div>

    <div class="panel comments">
      <h3>Comments</h3>
      <?php if (km_current_user()): ?>
        <form id="commentForm" onsubmit="return postComment(event, <?= $id ?>)">
          <?= km_csrf_field() ?>
          <textarea id="commentText" required></textarea><br>
          <button class="btn" type="submit">Post Comment</button>
          <button class="btn ghost" type="button" onclick="openEmoji()">😊</button>
        </form>
      <?php else: ?>
        <p><a href="/login.php">Log in</a> to comment.</p>
      <?php endif; ?>

      <div id="commentsList">
        <?php
        // simple flat render (hierarchy can be built client-side)
        foreach ($commentsList as $c) {
            if ($c['is_deleted']) { echo '<div class="comment">[deleted]</div>'; continue; }
            echo '<div class="comment"><div class="avatar">'.km_esc(substr($c['author'],0,1)).'</div><div class="cbody"><strong>'.km_esc($c['author']).'</strong> <small>'.km_esc($c['created_at']).'</small><div>'.nl2br(km_esc($c['body'])).'</div></div></div>';
        }
        ?>
      </div>
    </div>
  </div>

  <aside class="side-col">
    <div class="panel">
      <h4>Reddit Chat</h4>
      <?php if ($redRow): ?>
        <p><a href="<?= km_esc($redRow['reddit_thread_url'] ?: 'https://www.reddit.com/r/'.rawurlencode($redRow['reddit_subreddit'])) ?>" target="_blank">Open Reddit</a></p>
        <iframe src="<?= km_esc($redRow['reddit_thread_url'] ?: 'https://www.reddit.com/r/'.rawurlencode($redRow['reddit_subreddit'])) ?>" style="width:100%;height:300px;border:0"></iframe>
      <?php else: ?>
        <p>No reddit linked. <a href="/reddit_oauth.php?channel_id=<?= (int)$m['channel_id'] ?>">Link Reddit</a></p>
      <?php endif; ?>
    </div>
  </aside>
</section>

<script>
  // simple analytic fire on page load
  (function(){ fetch('/analytics.php?action=record_view', { method:'POST', body: new URLSearchParams({media_id: '<?= $id ?>'}) }); })();
</script>

<?php require_once __DIR__ . '/_includes/footer.php'; ?>