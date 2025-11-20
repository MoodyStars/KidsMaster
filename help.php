<?php
// help.php - consolidated help and FAQ
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Help & FAQ</h2>
  <h3>Uploading</h3>
  <p>Use the Upload UI to submit video, audio, images, software and games. Files are uploaded in chunks so you can resume on poor connections. You have a sample quota of 512GB per user — adjust in admin settings.</p>

  <h3>Channels</h3>
  <p>Each user gets a default channel on registration. Use Channel Edit to change PFP, GIF banner and background. Channel Setup toggles chat & live features.</p>

  <h3>Live streaming</h3>
  <p>Use the Live Streams page to create a stream and get an RTMP key for your encoder. HLS packaging is provided by the encoding worker (not included by default).</p>

  <h3>Moderation</h3>
  <p>Report content via the report links. Moderators can archive channels and delete comments.</p>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>