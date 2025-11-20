<?php
// contact.php - contact form page and AJAX handler (submits to ajax/contact_api.php)
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
$currentUser = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Contact — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <script>window.KM_USER = <?=json_encode($currentUser ?? null)?>; window.KM_CSRF=<?=json_encode(csrf_token())?>;</script>
  <script defer src="assets/js/contact.js"></script>
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
  <main class="container">
    <section class="panel">
      <h1>Contact Us</h1>
      <form id="contactForm">
        <?= csrf_field_html() ?>
        <label>Name<br><input type="text" name="name" id="contactName" value="<?=htmlspecialchars($currentUser['username'] ?? '')?>"></label>
        <label>Email<br><input type="email" name="email" id="contactEmail" value="<?=htmlspecialchars($currentUser['email'] ?? '')?>"></label>
        <label>Subject<br><input type="text" name="subject" id="contactSubject"></label>
        <label>Message<br><textarea name="body" id="contactBody" required></textarea></label>
        <br><button class="btn" type="submit">Send Message</button>
      </form>
      <div id="contactResult" style="margin-top:10px;"></div>
    </section>
  </main>
</body>
</html>