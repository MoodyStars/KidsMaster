<?php
// people.php - directory of popular creators
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();
$stmt = $pdo->prepare("SELECT u.id,u.username,u.avatar,COUNT(m.id) as uploads FROM users u LEFT JOIN channels c ON c.owner_id = u.id LEFT JOIN media m ON m.channel_id = c.id GROUP BY u.id ORDER BY uploads DESC LIMIT 100");
$stmt->execute();
$rows = $stmt->fetchAll();
?>
<section class="panel">
  <h2>People</h2>
  <div class="thumb-grid small">
    <?php foreach ($rows as $r): ?>
      <article class="thumb small"><a href="/member.php?u=<?= urlencode($r['username']) ?>"><div style="padding:8px; text-align:center;"><img src="<?= km_esc($r['avatar'] ?? '/assets/img/default_avatar.png') ?>" style="width:80px;height:80px;border-radius:50%"><h4><?= km_esc($r['username']) ?></h4><small><?= (int)$r['uploads'] ?> uploads</small></div></a></article>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>