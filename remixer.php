<?php
// remixer.php - audio remixing tool stub (client sends remix request, server queues worker)
require_once __DIR__ . '/_includes/init.php';
km_require_login();
require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Remixer (Beta)</h2>
  <p>Upload an audio stem or select from your uploads and request a simple remix. This service is powered by a background worker (not included in this demo).</p>
  <form method="post" action="/ajax/remix_api.php?action=create">
    <?= km_csrf_field() ?>
    <label>Source media ID <input name="media_id" type="number" required></label><br>
    <label>Effect preset
      <select name="preset">
        <option value="lofi">Lo-Fi</option>
        <option value="echo">Echo</option>
        <option value="spedup">Speed Up</option>
      </select>
    </label><br>
    <button class="btn">Queue Remix</button>
  </form>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>