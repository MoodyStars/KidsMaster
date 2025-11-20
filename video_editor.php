<?php
// video_editor.php - basic in-browser editor stub (trim/thumbnail selection). Integrate with ffmpeg worker for actual processing.
require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
require_once __DIR__ . '/_includes/header.php';
$media_id = (int)($_GET['id'] ?? 0);
?>
<section class="panel">
  <h2>Video Editor (Beta)</h2>
  <p>This is a simple editor: select start/end time and request a trimmed version. Heavy processing runs in the encoding worker.</p>
  <form method="post" action="/ajax/editor_api.php?action=trim">
    <?= km_csrf_field() ?>
    <input type="hidden" name="media_id" value="<?= $media_id ?>">
    <label>Start time (s) <input name="start" type="number" min="0" value="0"></label>
    <label>End time (s) <input name="end" type="number" min="1" value="10"></label>
    <button class="btn">Create Trimmed Clip</button>
  </form>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>