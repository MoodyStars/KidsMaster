<?php
// blogs.php - simple blog listing for site news & creator posts
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();
$stmt = $pdo->prepare("SELECT id,title,excerpt,created_at FROM blogs ORDER BY created_at DESC LIMIT 20");
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<section class="panel">
  <h2>Blogs</h2>
  <?php foreach ($rows as $b): ?>
    <article class="panel">
      <h3><a href="/blog.php?id=<?= (int)$b['id'] ?>"><?= km_esc($b['title']) ?></a></h3>
      <div class="km-retro-small"><?= km_esc($b['created_at']) ?></div>
      <p><?= km_esc($b['excerpt']) ?></p>
    </article>
  <?php endforeach; ?>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>