<?php
// community.php - community hub: groups, recent posts, members highlights
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();

// recent groups (reuse groups table if exists)
$groups = $pdo->query("SELECT id,name,description FROM `groups` ORDER BY created_at DESC LIMIT 12")->fetchAll();
$recentUsers = $pdo->query("SELECT username, last_seen FROM users ORDER BY last_seen DESC LIMIT 8")->fetchAll();
?>
<section class="panel">
  <h2>Community Hub</h2>
  <div style="display:flex;gap:18px;">
    <div style="flex:1">
      <h3>Groups</h3>
      <?php foreach ($groups as $g): ?>
        <div class="panel" style="margin-bottom:8px;">
          <h4><a href="/group.php?id=<?= (int)$g['id'] ?>"><?= km_esc($g['name']) ?></a></h4>
          <p class="km-retro-small"><?= km_esc(substr($g['description'] ?? '',0,200)) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <aside style="width:260px;">
      <div class="panel">
        <h4>Active Users</h4>
        <ul class="small-list">
          <?php foreach ($recentUsers as $u): ?><li><?= km_esc($u['username']) ?> <small><?= km_esc($u['last_seen']) ?></small></li><?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>