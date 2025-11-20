<?php
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();
$stmt = $pdo->prepare("SELECT id,title,thumbnail,views FROM media WHERE type='audio' ORDER BY created_at DESC LIMIT 48");
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<section class="panel">
  <h2>Audio</h2>
  <div class="thumb-grid">
    <?php foreach ($rows as $r): ?>
      <article class="thumb"><a href="/watch.php?id=<?= (int)$r['id'] ?>"><img src="<?= km_esc($r['thumbnail']) ?>"><div class="thumb-meta"><h4><?= km_esc($r['title']) ?></h4></div></a></article>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>