<?php
// special_videos.php - curated & special collections (events, staff picks, nostalgic lists)
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();

// curated picks: picks table optional fallback to featured flag
$picks = $pdo->query("SELECT id,title,thumbnail,views FROM media WHERE featured=1 ORDER BY updated_at DESC LIMIT 24")->fetchAll();
$nostalgia = $pdo->query("SELECT id,title,thumbnail FROM media WHERE category LIKE '%2000s%' OR tags LIKE '%2000s%' ORDER BY views DESC LIMIT 24")->fetchAll();
$staffPicks = $pdo->query("SELECT id,title,thumbnail FROM media WHERE is_staff_pick=1 ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<section class="panel">
  <h2>Curated Collections</h2>
  <h3>Featured</h3>
  <div class="thumb-grid">
    <?php foreach ($picks as $m): ?>
      <article class="thumb"><a href="/watch.php?id=<?= (int)$m['id'] ?>"><img src="<?= km_esc($m['thumbnail']) ?>"><div class="thumb-meta"><h4><?= km_esc($m['title']) ?></h4></div></a></article>
    <?php endforeach; ?>
  </div>

  <h3>2000s Nostalgic</h3>
  <div class="thumb-grid">
    <?php foreach ($nostalgia as $m): ?>
      <article class="thumb"><a href="/watch.php?id=<?= (int)$m['id'] ?>"><img src="<?= km_esc($m['thumbnail']) ?>"><div class="thumb-meta"><h4><?= km_esc($m['title']) ?></h4></div></a></article>
    <?php endforeach; ?>
  </div>

  <h3>Staff Picks</h3>
  <div class="thumb-grid">
    <?php foreach ($staffPicks as $m): ?>
      <article class="thumb"><a href="/watch.php?id=<?= (int)$m['id'] ?>"><img src="<?= km_esc($m['thumbnail']) ?>"><div class="thumb-meta"><h4><?= km_esc($m['title']) ?></h4></div></a></article>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>