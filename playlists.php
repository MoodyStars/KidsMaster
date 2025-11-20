<?php
// playlists.php - list and create playlists for user
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';
require_login();
$user = current_user();
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM playlists WHERE owner_id = :uid ORDER BY created_at DESC");
$stmt->execute([':uid'=>$user['id']]);
$pls = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>My Playlists</title><link rel="stylesheet" href="assets/css/style.css" /></head><body class="km-body">
<header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
<main class="container">
  <h1>My Playlists</h1>
  <section class="panel">
    <form id="createPl" method="post" action="ajax/playlists_api.php?action=create">
      <?= csrf_field_html() ?>
      <input name="name" placeholder="Playlist name" required>
      <label><input type="checkbox" name="is_public" checked> Public</label>
      <button class="btn" type="submit">Create</button>
    </form>
  </section>
  <section class="panel">
    <ul>
      <?php foreach ($pls as $p): ?>
        <li><a href="playlist.php?id=<?=htmlspecialchars($p['id'])?>"><?=htmlspecialchars($p['name'])?></a> (<?= $p['is_public']? 'Public':'Private' ?>)</li>
      <?php endforeach; ?>
    </ul>
  </section>
</main></body></html>