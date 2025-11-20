<?php
// storage.php - user's personal storage file browser
require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();
$stmt = $pdo->prepare("SELECT * FROM storage_files WHERE owner_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid'=>$user['id']]);
$files = $stmt->fetchAll();
?>
<section class="panel">
  <h2>My Storage</h2>
  <p>Quota: 512GB (example). Used: <?= number_format(api_get_user_storage_usage($user['id'])/1024/1024,2) ?> MB</p>
  <ul>
    <?php foreach ($files as $f): ?>
      <li><?= km_esc($f['file_name']) ?> — <?= number_format($f['size']/1024,2) ?> KB — <a href="<?= km_esc($f['path']) ?>">download</a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>