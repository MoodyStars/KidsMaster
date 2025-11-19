<?php
session_start();
require_once __DIR__ . '/api.php';

$type = $_GET['type'] ?? '';
$category = $_GET['category'] ?? '';
$tag = $_GET['tag'] ?? '';
$q = $_GET['q'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;

$params = [
  'type' => $type ?: null,
  'category' => $category ?: null,
  'tag' => $tag ?: null,
  'q' => $q ?: null,
  'page' => $page,
  'limit' => $perPage
];

$results = api_list_media($params);
$total = api_count_media($params);
$pages = (int)ceil($total / $perPage);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Browse — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <script defer src="assets/js/main.js"></script>
</head>
<body class="km-body">
  <header class="km-header">
    <div class="km-brand"><a href="index.php">KidsMaster</a></div>
    <nav class="km-nav"><a href="index.php">Home</a> | <a href="upload.php">Upload</a></nav>
  </header>

  <main class="container">
    <h1>Browse<?php if($type) echo " - ".htmlspecialchars(ucfirst($type)); ?></h1>
    <div class="browse-filters">
      <form id="browseFilters" onsubmit="return false;">
        <select id="filterType" onchange="applyFilters()">
          <option value="">All</option>
          <option value="video" <?= $type==='video'?'selected':'' ?>>Videos</option>
          <option value="audio" <?= $type==='audio'?'selected':'' ?>>Audio</option>
          <option value="image" <?= $type==='image'?'selected':'' ?>>Images</option>
          <option value="software" <?= $type==='software'?'selected':'' ?>>Software</option>
          <option value="game" <?= $type==='game'?'selected':'' ?>>Games</option>
        </select>
        <input id="filterQ" value="<?=htmlspecialchars($q)?>" placeholder="search..." />
        <select id="sortBy" onchange="applyFilters()">
          <option value="newest">Newest</option>
          <option value="views">Most Viewed</option>
          <option value="rating">Top Rated</option>
        </select>
        <button onclick="applyFilters()">Apply</button>
      </form>
    </div>

    <section class="thumb-grid large">
      <?php foreach ($results as $r): ?>
        <article class="thumb">
          <a href="watch.php?id=<?=htmlspecialchars($r['id'])?>">
            <img src="<?=htmlspecialchars($r['thumbnail'])?>" alt="<?=htmlspecialchars($r['title'])?>" />
            <div class="thumb-meta">
              <h4><?=htmlspecialchars($r['title'])?></h4>
              <small><?=htmlspecialchars($r['views'])?> views • <?=htmlspecialchars($r['duration'] ?? '')?></small>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </section>

    <div class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
        <a class="page <?= $i==$page ? 'active' : '' ?>" href="?<?=http_build_query(array_merge($_GET,['page'=>$i]))?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>

  </main>
</body>
</html>