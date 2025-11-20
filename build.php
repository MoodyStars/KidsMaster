<?php
// build.php - builder page (projects, saves, simple file builder reference)
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();
?>
<section class="panel">
  <h2>Build</h2>
  <p>Host builds, share creative remixes and demos. This area is a project workspace for creators to upload builds (zip packages) and link documentation.</p>
  <p><a href="/upload_ui.php">Upload a build</a></p>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>