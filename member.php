<?php
// member.php - public profile for a user with their channels and latest uploads
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();

$username = trim($_GET['u'] ?? '');
if (!$username) { header('Location:/people.php'); exit; }
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
$stmt->execute([':u'=>$username]);
$u = $stmt->fetch();
if (!$u) { echo '<div class="panel">User not found</div>'; require_once __DIR__ . '/_includes/footer.php'; exit; }
$channels = $pdo->prepare("SELECT * FROM channels WHERE owner_id = :uid");
$channels->execute([':uid'=>$u['id']]);
$channels = $channels->fetchAll();
$uploads = $pdo->prepare("SELECT * FROM media WHERE channel_id IN (SELECT id FROM channels WHERE owner_id = :uid) ORDER BY created_at DESC LIMIT 24");
$uploads->execute([':uid'=>$u['id']]); $uploads = $uploads->fetchAll();
?>
<section class="panel">
  <h2><?= km_esc($u['username']) ?></h2>
  <div class="km-retro-small">Member since <?= km_esc($u['created_at']) ?></div>

  <h3>Channels</h3>
  <ul>
    <?php foreach ($channels as $c): ?>
      <li><a href="/channel.php?id=<?= (int)$c['id'] ?>"><?= km_esc($c['name']) ?></a></li>
    <?php endforeach; ?>
  </ul>

  <h3>Recent Uploads</h3>
  <div class="thumb-grid">
    <?php foreach ($uploads as $m): ?>
      <article class="thumb"><a href="/watch.php?id=<?= (int)$m['id'] ?>"><img src="<?= km_esc($m['thumbnail']) ?>"><div class="thumb-meta"><h4><?= km_esc($m['title']) ?></h4></div></a></article>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>