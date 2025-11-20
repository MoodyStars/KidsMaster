<?php
// categories.php - show categories and link to browse by category
session_start();
require_once __DIR__ . '/includes/api_cat_group.php';
require_once __DIR__ . '/includes/auth.php';

$categories = api_list_categories();
$currentUser = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Categories — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <style>
    .categories-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
    .cat-card { background:#fff;padding:12px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.04); }
    .cat-card h3{ margin:0 0 6px 0; }
    .cat-card p{ margin:0; color:#666; font-size:13px; }
  </style>
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>
  <main class="container" style="max-width:1100px;margin:20px auto;">
    <h1>Categories</h1>
    <div class="categories-grid">
      <?php foreach ($categories as $c): ?>
        <div class="cat-card">
          <h3><a href="browse.php?category=<?=urlencode($c['title'] ?: $c['slug'])?>"><?=htmlspecialchars($c['title'])?></a></h3>
          <p><?=htmlspecialchars($c['description'] ?? '')?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</body>
</html>