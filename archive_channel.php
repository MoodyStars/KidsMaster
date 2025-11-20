<?php
// archive_channel.php - single channel archive view (owner/moderator)
require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
$pdo = km_db();

$channel_id = (int)($_GET['id'] ?? 0);
if (!$channel_id) { header('Location:/channels.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM channels WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$channel_id]); $ch = $stmt->fetch();
if (!$ch) { http_response_code(404); exit; }
if ($ch['owner_id'] != $user['id'] && empty($user['is_moderator'])) { http_response_code(403); exit; }

$videos = $pdo->prepare("SELECT * FROM media WHERE channel_id = :cid AND is_archived = 1 ORDER BY created_at DESC");
$videos->execute([':cid'=>$channel_id]);
$rows = $videos->fetchAll();

require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Archived for <?= km_esc($ch['name']) ?></h2>
  <div class="thumb-grid">
    <?php foreach ($rows as $r): ?>
      <article class="thumb"><a href="/watch.php?id=<?= (int)$r['id'] ?>"><img src="<?= km_esc($r['thumbnail']) ?>"><div class="thumb-meta"><h4><?= km_esc($r['title']) ?></h4></div></a></article>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>