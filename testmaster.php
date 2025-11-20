<?php
// testmaster.php - developer debug page with environment checks & quick tools
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';

$pdo = km_db();
$php = phpversion();
$dsn = 'ok';
$redis = extension_loaded('redis') ? 'available' : 'missing';
$ffmpeg = shell_exec('which ffmpeg') ? 'found' : 'not found';
$storageWritable = is_writable(__DIR__ . '/storage') ? 'writable' : 'not writable';

?>
<section class="panel">
  <h2>Dev Diagnostics</h2>
  <ul>
    <li>PHP: <?= km_esc($php) ?></li>
    <li>DB: connection <?= km_esc($dsn) ?></li>
    <li>Redis: <?= km_esc($redis) ?></li>
    <li>ffmpeg: <?= km_esc($ffmpeg) ?></li>
    <li>Storage dir: <?= km_esc($storageWritable) ?></li>
  </ul>

  <h3>Quick Actions</h3>
  <form method="post" action="/ajax/admin_api.php?action=run_sql">
    <?= km_csrf_field() ?>
    <label>Run SQL (dev only)<br><textarea name="sql" style="width:100%;height:80px"></textarea></label><br>
    <button class="btn">Run</button>
  </form>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>